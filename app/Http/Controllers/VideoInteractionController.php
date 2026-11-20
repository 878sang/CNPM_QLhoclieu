<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Document;
use App\Models\User;
use App\Models\VideoInteraction;
use App\Models\VideoInteractionResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VideoInteractionController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');

        $coursesQuery = Course::orderBy('title');
        if (! $isAdmin) {
            $coursesQuery->where('teacher_id', $user->id);
        }
        $courses = $coursesQuery->get();
        $courseIds = $courses->pluck('id');

        $selectedCourseId = $request->filled('course_id') ? $request->integer('course_id') : null;
        $search = trim((string) $request->input('q', ''));
        $questionType = $request->input('question_type');

        // Danh sách các video bài giảng thuộc các khóa học quản lý
        $videoDocsQuery = Document::with(['course:id,title', 'lesson:id,title'])
            ->withCount('videoInteractions')
            ->whereIn('course_id', $courseIds)
            ->where(function ($q): void {
                $q->where('type', 'video')
                    ->orWhere('mime_type', 'like', 'video/%');
            })
            ->when($selectedCourseId, fn($q) => $q->where('course_id', $selectedCourseId))
            ->when($search !== '', fn($q) => $q->where('title', 'like', "%{$search}%"))
            ->latest();

        $videoDocuments = $videoDocsQuery->get();

        // Danh sách chi tiết các câu hỏi tương tác trên video
        $videoInteractions = VideoInteraction::with(['course:id,title', 'lesson:id,title', 'document:id,title,course_id'])
            ->withCount([
                'responses as total_responses_count',
                'responses as correct_responses_count' => fn($q) => $q->where('is_correct', true),
            ])
            ->whereIn('course_id', $courseIds)
            ->when($selectedCourseId, fn($q) => $q->where('course_id', $selectedCourseId))
            ->when($questionType && array_key_exists($questionType, VideoInteraction::TYPES), fn($q) => $q->where('question_type', $questionType))
            ->when($search !== '', fn($q) => $q->where('question', 'like', "%{$search}%"))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $allQuestionsCount = VideoInteraction::whereIn('course_id', $courseIds)->count();
        $videosWithQuestionsCount = VideoInteraction::whereIn('course_id', $courseIds)->distinct('document_id')->count('document_id');
        $totalResponses = VideoInteractionResponse::whereHas('interaction', fn($q) => $q->whereIn('course_id', $courseIds))->count();
        $correctResponses = VideoInteractionResponse::where('is_correct', true)
            ->whereHas('interaction', fn($q) => $q->whereIn('course_id', $courseIds))
            ->count();

        return view('video-interactions.index', [
            'courses' => $courses,
            'videoDocuments' => $videoDocuments,
            'videoInteractions' => $videoInteractions,
            'stats' => [
                'total_videos' => $videoDocuments->count(),
                'videos_with_questions' => $videosWithQuestionsCount,
                'total_questions' => $allQuestionsCount,
                'total_responses' => $totalResponses,
                'correct_rate' => $totalResponses > 0 ? (int) round($correctResponses * 100 / $totalResponses) : 0,
            ],
        ]);
    }

    public function manage(Course $course, Document $document): View
    {
        $this->authorize('update', $course);
        abort_unless($document->course_id === $course->id && $document->isVideo(), 404);

        $interactions = $document->videoInteractions()
            ->withCount([
                'responses as total_responses_count',
                'responses as correct_responses_count' => fn($q) => $q->where('is_correct', true),
            ])
            ->with(['responses' => fn($q) => $q->with('user:id,name,email')->latest('answered_at')->limit(5)])
            ->orderBy('timestamp_seconds')
            ->orderBy('id')
            ->get();

        $totalAttempts = VideoInteractionResponse::whereIn('video_interaction_id', $interactions->pluck('id'))->count();
        $totalCorrect = VideoInteractionResponse::whereIn('video_interaction_id', $interactions->pluck('id'))
            ->where('is_correct', true)
            ->count();

        $checkpointCount = $interactions->pluck('timestamp_seconds')->unique()->count();

        return view('video-interactions.manage', [
            'course' => $course,
            'document' => $document->load('lesson.chapter'),
            'interactions' => $interactions,
            'checkpointCount' => $checkpointCount,
            'totalAttempts' => $totalAttempts,
            'correctRate' => $totalAttempts > 0 ? (int) round($totalCorrect * 100 / $totalAttempts) : 0,
        ]);
    }

    public function store(Request $request, Course $course, Document $document): RedirectResponse
    {
        $this->authorize('update', $course);
        abort_unless($document->course_id === $course->id && $document->isVideo(), 404);

        // Hỗ trợ thêm nhiều câu hỏi cùng lúc (items[]) hoặc thêm 1 câu lẻ
        if ($request->has('items') && is_array($request->input('items'))) {
            $rawItems = array_values($request->input('items'));
            if (empty($rawItems)) {
                throw ValidationException::withMessages([
                    'items' => 'Vui lòng thêm ít nhất 1 câu hỏi tương tác.',
                ]);
            }

            $prepared = [];
            foreach ($rawItems as $index => $item) {
                $prepared[] = $this->validateSingleItem(
                    is_array($item) ? $item : [],
                    "Câu hỏi #" . ($index + 1)
                );
            }

            DB::transaction(function () use ($document, $course, $prepared): void {
                foreach ($prepared as $data) {
                    $document->videoInteractions()->create([
                        ...$data,
                        'course_id' => $course->id,
                        'lesson_id' => $document->lesson_id,
                        'created_by' => Auth::id(),
                    ]);
                }
            });

            $count = count($prepared);

            return redirect()
                ->route('courses.video-interactions.manage', [$course, $document])
                ->with('status', "Đã thêm thành công {$count} câu hỏi tương tác trên video.");
        }

        $validated = $this->validateSingleItem($request->all());

        $document->videoInteractions()->create([
            ...$validated,
            'course_id' => $course->id,
            'lesson_id' => $document->lesson_id,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('courses.video-interactions.manage', [$course, $document])
            ->with('status', 'Đã thêm câu hỏi tương tác trên video thành công.');
    }

    public function update(Request $request, Course $course, Document $document, VideoInteraction $interaction): RedirectResponse
    {
        $this->authorize('update', $course);
        abort_unless($document->course_id === $course->id && $interaction->document_id === $document->id, 404);

        $validated = $this->validateSingleItem($request->all());
        $interaction->update($validated);

        return redirect()
            ->route('courses.video-interactions.manage', [$course, $document])
            ->with('status', 'Đã cập nhật câu hỏi tương tác trên video.');
    }

    public function destroy(Course $course, Document $document, VideoInteraction $interaction): RedirectResponse
    {
        $this->authorize('update', $course);
        abort_unless($document->course_id === $course->id && $interaction->document_id === $document->id, 404);

        $interaction->delete();

        return redirect()
            ->route('courses.video-interactions.manage', [$course, $document])
            ->with('status', 'Đã xóa câu hỏi tương tác khỏi video.');
    }

    public function answer(Request $request, VideoInteraction $interaction): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $course = $interaction->course;

        $isEnrolled = $course->enrollments()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        abort_unless($isEnrolled || $user->can('update', $course), 403, 'Bạn không có quyền trả lời câu hỏi này.');

        $data = $request->validate([
            'answer' => ['required', 'string', 'max:500'],
        ]);

        $selected = trim($data['answer']);
        $isCorrect = $interaction->checkAnswer($selected);

        $response = VideoInteractionResponse::firstOrNew([
            'video_interaction_id' => $interaction->id,
            'user_id' => $user->id,
        ]);

        $response->selected_answer = $selected;
        $response->is_correct = $isCorrect;
        $response->attempts = $response->exists ? ((int) $response->attempts + 1) : 1;
        $response->answered_at = now();
        $response->save();

        $totalInVideo = VideoInteraction::where('document_id', $interaction->document_id)->count();
        $userResponses = VideoInteractionResponse::where('user_id', $user->id)
            ->whereHas('interaction', fn($q) => $q->where('document_id', $interaction->document_id))
            ->get();

        $answeredCount = $userResponses->count();
        $correctCount = $userResponses->where('is_correct', true)->count();
        $score10 = $totalInVideo > 0 ? round($correctCount * 10 / $totalInVideo, 1) : null;
        $accuracyPct = $totalInVideo > 0 ? (int) round($correctCount * 100 / $totalInVideo) : null;

        return response()->json([
            'success' => true,
            'interaction_id' => $interaction->id,
            'is_correct' => $isCorrect,
            'selected_answer' => $selected,
            'correct_answer' => $interaction->correct_answer,
            'correct_answer_label' => $interaction->correctAnswerLabel(),
            'explanation' => $interaction->explanation,
            'attempts' => $response->attempts,
            'stats' => [
                'total' => $totalInVideo,
                'answered' => $answeredCount,
                'correct' => $correctCount,
                'score_10' => $score10,
                'accuracy_percent' => $accuracyPct,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateSingleItem(array $input, string $prefix = ''): array
    {
        $label = $prefix !== '' ? "{$prefix}: " : '';
        $timestamp = $this->parseTimestampSeconds($input['timestamp_input'] ?? null, $input['timestamp_seconds'] ?? null);
        $input['timestamp_seconds'] = $timestamp;

        $validator = validator($input, [
            'timestamp_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'question_type' => ['required', Rule::in(array_keys(VideoInteraction::TYPES))],
            'question' => ['required', 'string', 'max:1000'],
            'option_a' => ['nullable', 'string', 'max:500'],
            'option_b' => ['nullable', 'string', 'max:500'],
            'option_c' => ['nullable', 'string', 'max:500'],
            'option_d' => ['nullable', 'string', 'max:500'],
            'correct_answer' => ['required', 'string', 'max:500'],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable'],
        ], [
            'timestamp_seconds.required' => "{$label}Vui lòng nhập mốc thời gian hợp lệ (VD: 01:30 hoặc 90).",
            'question.required' => "{$label}Vui lòng nhập nội dung câu hỏi.",
            'correct_answer.required' => "{$label}Vui lòng chọn hoặc nhập đáp án đúng.",
        ]);

        $validated = $validator->validate();

        $type = $validated['question_type'];
        $options = null;
        $correctAnswer = trim((string) $validated['correct_answer']);

        if ($type === VideoInteraction::TYPE_MULTIPLE_CHOICE) {
            $options = array_filter([
                'A' => trim((string) ($validated['option_a'] ?? '')),
                'B' => trim((string) ($validated['option_b'] ?? '')),
                'C' => trim((string) ($validated['option_c'] ?? '')),
                'D' => trim((string) ($validated['option_d'] ?? '')),
            ], fn(string $v) => $v !== '');

            if (count($options) < 2) {
                throw ValidationException::withMessages([
                    'option_a' => "{$label}Câu hỏi trắc nghiệm (1 đáp án đúng) cần ít nhất 2 phương án trả lời.",
                ]);
            }

            // Trắc nghiệm chỉ có 1 đáp án đúng duy nhất (A, B, C hoặc D)
            $correctAnswer = strtoupper($correctAnswer);
            if (! array_key_exists($correctAnswer, $options)) {
                throw ValidationException::withMessages([
                    'correct_answer' => "{$label}Đáp án đúng đã chọn phải thuộc các phương án có nội dung.",
                ]);
            }
        } elseif ($type === VideoInteraction::TYPE_TRUE_FALSE) {
            $options = [
                'true' => 'Đúng',
                'false' => 'Sai',
            ];
            $correctAnswer = in_array(strtolower($correctAnswer), ['true', '1', 'đúng', 'dung'], true) ? 'true' : 'false';
        }

        $isRequired = array_key_exists('is_required', $input)
            ? filter_var($input['is_required'], FILTER_VALIDATE_BOOLEAN)
            : false;

        return [
            'timestamp_seconds' => (int) $validated['timestamp_seconds'],
            'question_type' => $type,
            'question' => trim($validated['question']),
            'options' => $options,
            'correct_answer' => $correctAnswer,
            'explanation' => filled($validated['explanation'] ?? null) ? trim((string) $validated['explanation']) : null,
            'is_required' => $isRequired,
        ];
    }

    private function parseTimestampSeconds(mixed $timestampInput, mixed $rawSeconds): ?int
    {
        if (is_string($timestampInput) && trim($timestampInput) !== '') {
            $text = trim($timestampInput);
            if (preg_match('/^\d+$/', $text)) {
                return (int) $text;
            }
            if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $text, $m)) {
                if (isset($m[3]) && $m[3] !== '') {
                    return ((int) $m[1] * 3600) + ((int) $m[2] * 60) + (int) $m[3];
                }

                return ((int) $m[1] * 60) + (int) $m[2];
            }
        }

        if ($rawSeconds !== null && $rawSeconds !== '') {
            return max(0, (int) $rawSeconds);
        }

        return null;
    }
}
