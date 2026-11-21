<?php

namespace App\Http\Controllers;

use App\Models\AiTutorMessage;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Services\GeminiTutorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiTutorController extends Controller
{
    public function __construct(private GeminiTutorService $tutorService) {}

    /**
     * Đọc ngầm video & tài liệu bài học ngay khi học viên mở trang bài giảng
     * (giải phóng session lock trước khi gọi Gemini để không chặn thao tác khác của học viên).
     */
    public function prepare(Course $course, Lesson $lesson): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($lesson->chapter && $lesson->chapter->course_id === $course->id, 404);

        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists();
        abort_unless($isEnrolled || $user->can('update', $course), 403);

        if (! $this->tutorService->isConfigured()) {
            return response()->json(['success' => true, 'status' => 'unconfigured']);
        }

        $lesson->load([
            'chapter',
            'documents' => fn ($q) => $q->approved(),
        ]);
        $course->loadMissing('subject');

        if ($this->tutorService->hasFreshContext($lesson)) {
            return response()->json(['success' => true, 'status' => 'ready', 'cached' => true]);
        }

        if (Cache::has("ai_prepare_cooldown_{$lesson->id}")) {
            return response()->json(['success' => true, 'status' => 'cooldown', 'cached' => false]);
        }

        // Trong môi trường PHPUnit, chạy trực tiếp để test kiểm chứng Http::fake()
        if (app()->runningUnitTests()) {
            $lock = Cache::lock("ai_prepare_lesson_{$lesson->id}", 180);
            if (! $lock->get()) {
                return response()->json(['success' => true, 'status' => 'preparing']);
            }

            try {
                $result = $this->tutorService->prepareLessonContext($course, $lesson);

                return response()->json([
                    'success' => true,
                    'status' => $result['status'],
                    'cached' => $result['cached'],
                ]);
            } finally {
                $lock->release();
            }
        }

        // Khởi chạy tiến trình PHP CLI độc lập hoàn toàn ở nền để KHÔNG BAO GIỜ khóa luồng web (đặc biệt trên php artisan serve Windows)
        if (Cache::add("ai_prepare_spawned_{$lesson->id}", true, 90)) {
            $this->spawnBackgroundPrepare($course->id, $lesson->id);
        }

        return response()->json([
            'success' => true,
            'status' => 'preparing',
            'cached' => false,
        ]);
    }

    private function spawnBackgroundPrepare(int $courseId, int $lessonId): void
    {
        $phpBinary = $this->resolvePhpCliBinary();
        $cwd = getcwd() ?: '';
        if ($cwd !== '' && is_file($cwd.DIRECTORY_SEPARATOR.'artisan')) {
            $artisan = 'artisan';
        } elseif ($cwd !== '' && is_file(dirname($cwd).DIRECTORY_SEPARATOR.'artisan')) {
            $artisan = '..'.DIRECTORY_SEPARATOR.'artisan';
        } else {
            $artisan = base_path('artisan');
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = sprintf(
                'start "" /B %s %s ai:prepare-lesson %d %d > NUL 2>&1',
                escapeshellarg($phpBinary),
                escapeshellarg($artisan),
                $courseId,
                $lessonId
            );
            $handle = @popen($cmd, 'r');
            if (is_resource($handle)) {
                @pclose($handle);
            }
        } else {
            $cmd = sprintf(
                'nohup %s %s ai:prepare-lesson %d %d > /dev/null 2>&1 &',
                escapeshellarg($phpBinary),
                escapeshellarg($artisan),
                $courseId,
                $lessonId
            );
            @exec($cmd);
        }
    }

    private function resolvePhpCliBinary(): string
    {
        $binary = PHP_BINARY;
        if (PHP_OS_FAMILY === 'Windows' && str_ends_with(strtolower($binary), 'php-cgi.exe')) {
            $cliCandidate = substr($binary, 0, -11).'php.exe';
            if (is_file($cliCandidate)) {
                return $cliCandidate;
            }
        }

        return $binary ?: 'php';
    }

    public function ask(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        /** @var User $user */
        $user = Auth::user();
        abort_unless($lesson->chapter && $lesson->chapter->course_id === $course->id, 404);

        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists();
        abort_unless($isEnrolled || $user->can('update', $course), 403, 'Bạn cần tham gia khóa học để sử dụng Trợ lý AI.');

        $validated = $request->validate([
            'question' => ['nullable', 'string', 'max:2000', 'required_without:image'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'video_timestamp_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ], [
            'question.required_without' => 'Vui lòng nhập câu hỏi hoặc chọn hình ảnh để gửi cho Trợ lý AI.',
            'image.image' => 'Tệp đính kèm phải là hình ảnh hợp lệ (JPG, PNG, WEBP, GIF).',
            'image.max' => 'Ảnh đính kèm tối đa 5MB.',
        ]);

        $uploadedImage = $request->file('image');
        $question = trim((string) ($validated['question'] ?? ''));
        if ($question === '' && $uploadedImage) {
            $question = 'Hãy xem hình ảnh này và giải thích chi tiết giúp mình.';
        }

        $timestampSeconds = isset($validated['video_timestamp_seconds']) && $validated['video_timestamp_seconds'] !== ''
            ? (int) $validated['video_timestamp_seconds']
            : null;

        $imageAttachment = null;
        if ($uploadedImage && $uploadedImage->isValid()) {
            $rawBytes = file_get_contents($uploadedImage->getRealPath());
            if ($rawBytes !== false && $rawBytes !== '') {
                $imageAttachment = [
                    'data' => base64_encode($rawBytes),
                    'mime_type' => $uploadedImage->getMimeType() ?: 'image/jpeg',
                ];
            }
        }

        $lesson->load([
            'chapter',
            'documents' => fn ($q) => $q->approved(),
        ]);
        $course->loadMissing('subject');

        $lastAssistant = AiTutorMessage::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->where('role', AiTutorMessage::ROLE_ASSISTANT)
            ->whereNotNull('gemini_interaction_id')
            ->latest('id')
            ->first();

        try {
            $result = $this->tutorService->answerLessonQuestion(
                $course,
                $lesson,
                $question,
                $timestampSeconds,
                $lastAssistant?->gemini_interaction_id,
                $imageAttachment
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $storedImagePath = null;
        if ($uploadedImage && $uploadedImage->isValid()) {
            $storedImagePath = $uploadedImage->store("ai-tutor/{$user->id}");
        }

        $userMsg = AiTutorMessage::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'role' => AiTutorMessage::ROLE_USER,
            'content' => $question,
            'image_path' => $storedImagePath,
            'video_timestamp_seconds' => $timestampSeconds,
        ]);

        $aiMsg = AiTutorMessage::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'role' => AiTutorMessage::ROLE_ASSISTANT,
            'content' => $result['answer'],
            'gemini_interaction_id' => $result['interaction_id'],
            'sources_used' => ! empty($result['sources_used']) ? $result['sources_used'] : ($lastAssistant?->sources_used ?? []),
        ]);

        return response()->json([
            'success' => true,
            'cached' => (bool) ($result['cached'] ?? false),
            'user_message' => [
                'id' => $userMsg->id,
                'role' => $userMsg->role,
                'content' => $userMsg->content,
                'image_url' => $userMsg->imageUrl(),
                'formatted_timestamp' => $userMsg->formattedTimestamp(),
                'video_timestamp_seconds' => $userMsg->video_timestamp_seconds,
                'created_at' => $userMsg->created_at->format('H:i'),
            ],
            'assistant_message' => [
                'id' => $aiMsg->id,
                'role' => $aiMsg->role,
                'content' => $aiMsg->content,
                'created_at' => $aiMsg->created_at->format('H:i'),
            ],
        ]);
    }

    /**
     * Tối ưu #1: Trả lời theo luồng SSE (chữ hiện dần ngay lập tức giống ChatGPT/Gemini).
     */
    public function stream(Request $request, Course $course, Lesson $lesson): StreamedResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        /** @var User $user */
        $user = Auth::user();
        abort_unless($lesson->chapter && $lesson->chapter->course_id === $course->id, 404);

        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists();
        abort_unless($isEnrolled || $user->can('update', $course), 403, 'Bạn cần tham gia khóa học để sử dụng Trợ lý AI.');

        $validated = $request->validate([
            'question' => ['nullable', 'string', 'max:2000', 'required_without:image'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'video_timestamp_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ], [
            'question.required_without' => 'Vui lòng nhập câu hỏi hoặc chọn hình ảnh để gửi cho Trợ lý AI.',
            'image.image' => 'Tệp đính kèm phải là hình ảnh hợp lệ (JPG, PNG, WEBP, GIF).',
            'image.max' => 'Ảnh đính kèm tối đa 5MB.',
        ]);

        $uploadedImage = $request->file('image');
        $question = trim((string) ($validated['question'] ?? ''));
        if ($question === '' && $uploadedImage) {
            $question = 'Hãy xem hình ảnh này và giải thích chi tiết giúp mình.';
        }

        $timestampSeconds = isset($validated['video_timestamp_seconds']) && $validated['video_timestamp_seconds'] !== ''
            ? (int) $validated['video_timestamp_seconds']
            : null;

        $imageAttachment = null;
        $storedImagePath = null;
        if ($uploadedImage && $uploadedImage->isValid()) {
            $rawBytes = file_get_contents($uploadedImage->getRealPath());
            if ($rawBytes !== false && $rawBytes !== '') {
                $imageAttachment = [
                    'data' => base64_encode($rawBytes),
                    'mime_type' => $uploadedImage->getMimeType() ?: 'image/jpeg',
                ];
            }
            $storedImagePath = $uploadedImage->store("ai-tutor/{$user->id}");
        }

        $lesson->load([
            'chapter',
            'documents' => fn ($q) => $q->approved(),
        ]);
        $course->loadMissing('subject');

        $lastAssistant = AiTutorMessage::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->where('role', AiTutorMessage::ROLE_ASSISTANT)
            ->whereNotNull('gemini_interaction_id')
            ->latest('id')
            ->first();

        // Giải phóng khóa session để người dùng vẫn thao tác bình thường trong lúc AI stream chữ
        if (session()->isStarted()) {
            session()->save();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return response()->stream(function () use (
            $user,
            $course,
            $lesson,
            $question,
            $timestampSeconds,
            $lastAssistant,
            $imageAttachment,
            $storedImagePath
        ) {
            $emit = function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            try {
                $result = $this->tutorService->streamLessonQuestion(
                    $course,
                    $lesson,
                    $question,
                    $timestampSeconds,
                    $lastAssistant?->gemini_interaction_id,
                    $imageAttachment,
                    function (string $textDelta) use ($emit): void {
                        $emit('delta', ['text' => $textDelta]);
                    }
                );

                $userMsg = AiTutorMessage::create([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'lesson_id' => $lesson->id,
                    'role' => AiTutorMessage::ROLE_USER,
                    'content' => $question,
                    'image_path' => $storedImagePath,
                    'video_timestamp_seconds' => $timestampSeconds,
                ]);

                $aiMsg = AiTutorMessage::create([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'lesson_id' => $lesson->id,
                    'role' => AiTutorMessage::ROLE_ASSISTANT,
                    'content' => $result['answer'],
                    'gemini_interaction_id' => $result['interaction_id'],
                    'sources_used' => ! empty($result['sources_used']) ? $result['sources_used'] : ($lastAssistant?->sources_used ?? []),
                ]);

                $emit('done', [
                    'success' => true,
                    'cached' => (bool) ($result['cached'] ?? false),
                    'user_message' => [
                        'id' => $userMsg->id,
                        'role' => $userMsg->role,
                        'content' => $userMsg->content,
                        'image_url' => $userMsg->imageUrl(),
                        'formatted_timestamp' => $userMsg->formattedTimestamp(),
                        'video_timestamp_seconds' => $userMsg->video_timestamp_seconds,
                        'created_at' => $userMsg->created_at->format('H:i'),
                    ],
                    'assistant_message' => [
                        'id' => $aiMsg->id,
                        'role' => $aiMsg->role,
                        'content' => $aiMsg->content,
                        'created_at' => $aiMsg->created_at->format('H:i'),
                    ],
                ]);
            } catch (\Throwable $e) {
                if ($storedImagePath && Storage::exists($storedImagePath)) {
                    Storage::delete($storedImagePath);
                }
                $emit('error', ['message' => $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function image(AiTutorMessage $message): StreamedResponse
    {
        abort_unless($message->user_id === Auth::id(), 403);
        abort_unless($message->image_path && Storage::exists($message->image_path), 404);

        return Storage::response($message->image_path);
    }

    public function reset(Course $course, Lesson $lesson): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($lesson->chapter && $lesson->chapter->course_id === $course->id, 404);

        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists();
        abort_unless($isEnrolled || $user->can('update', $course), 403);

        $messages = AiTutorMessage::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->get();

        foreach ($messages as $msg) {
            if ($msg->image_path && Storage::exists($msg->image_path)) {
                Storage::delete($msg->image_path);
            }
        }

        AiTutorMessage::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->delete();

        return response()->json(['success' => true]);
    }
}
