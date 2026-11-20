<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonInteraction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InteractionController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');
        $tab = $request->query('tab') === 'discussions' ? 'discussions' : 'questions';

        $coursesQuery = Course::orderBy('title');
        if (! $isAdmin) {
            $coursesQuery->where('teacher_id', $user->id);
        }
        $courses = $coursesQuery->get();
        $courseIds = $courses->pluck('id');

        $statRow = LessonInteraction::whereIn('course_id', $courseIds)
            ->whereNull('parent_id')
            ->selectRaw("
                SUM(CASE WHEN type = 'question' THEN 1 ELSE 0 END) as questions_count,
                SUM(CASE WHEN type IN ('discussion', 'comment') THEN 1 ELSE 0 END) as discussions_count,
                SUM(CASE WHEN type = 'question' AND status = 'open' THEN 1 ELSE 0 END) as open_discussions,
                SUM(CASE WHEN type = 'question' AND status = 'resolved' THEN 1 ELSE 0 END) as resolved_discussions,
                SUM(CASE WHEN is_pinned = 1 THEN 1 ELSE 0 END) as pinned_discussions
            ")
            ->first();

        $stats = [
            'questions_count' => (int) ($statRow->questions_count ?? 0),
            'discussions_count' => (int) ($statRow->discussions_count ?? 0),
            'open_discussions' => (int) ($statRow->open_discussions ?? 0),
            'resolved_discussions' => (int) ($statRow->resolved_discussions ?? 0),
            'pinned_discussions' => (int) ($statRow->pinned_discussions ?? 0),
        ];

        $interactions = LessonInteraction::with([
            'course:id,title',
            'lesson:id,title,chapter_id',
            'lesson.documents:id,course_id,lesson_id,title,type,url,file_path,mime_type,original_name',
            'user:id,name,role_id',
            'user.role:id,name,display_name',
            'replies.user:id,name,role_id',
            'replies.user.role:id,name,display_name',
        ])
            ->whereIn('course_id', $courseIds)
            ->whereNull('parent_id')
            ->when(
                $tab === 'discussions',
                fn ($q) => $q->whereIn('type', [LessonInteraction::TYPE_DISCUSSION, LessonInteraction::TYPE_COMMENT]),
                fn ($q) => $q->where('type', LessonInteraction::TYPE_QUESTION)
            )
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->boolean('has_timestamp'), fn ($q) => $q->whereNotNull('video_timestamp_seconds'))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = trim((string) $request->input('q'));
                $q->where(function ($sub) use ($term): void {
                    $sub->where('content', 'like', "%{$term}%")
                        ->orWhereRelation('user', 'name', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('interactions.index', [
            'tab' => $tab,
            'courses' => $courses,
            'stats' => $stats,
            'interactions' => $interactions,
        ]);
    }

    public function store(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($lesson->chapter && $lesson->chapter->course_id === $course->id, 404);

        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists();
        $canManage = $user->can('update', $course);
        abort_unless($isEnrolled || $canManage, 403, 'Bạn cần tham gia khóa học để tương tác.');

        $parsedTimestamp = $this->parseTimestampSeconds(
            $request->input('video_timestamp_input'),
            $request->input('video_timestamp_seconds')
        );
        if ($parsedTimestamp !== null) {
            $request->merge(['video_timestamp_seconds' => $parsedTimestamp]);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'type' => ['nullable', Rule::in([LessonInteraction::TYPE_QUESTION, LessonInteraction::TYPE_DISCUSSION, LessonInteraction::TYPE_COMMENT])],
            'parent_id' => ['nullable', 'integer', 'exists:lesson_interactions,id'],
            'video_timestamp_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ], [
            'content.required' => 'Vui lòng nhập nội dung câu hỏi hoặc thảo luận.',
        ]);

        $parent = null;
        if (! empty($validated['parent_id'])) {
            $parent = LessonInteraction::where('course_id', $course->id)
                ->where('lesson_id', $lesson->id)
                ->findOrFail($validated['parent_id']);
        }

        $type = $parent ? LessonInteraction::TYPE_DISCUSSION : ($validated['type'] ?? LessonInteraction::TYPE_QUESTION);
        if ($type === LessonInteraction::TYPE_COMMENT) {
            $type = LessonInteraction::TYPE_DISCUSSION;
        }

        $interaction = LessonInteraction::create([
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'parent_id' => $parent?->id,
            'type' => $type,
            'content' => trim($validated['content']),
            'video_timestamp_seconds' => $validated['video_timestamp_seconds'] ?? null,
            'status' => LessonInteraction::STATUS_OPEN,
        ]);

        // Gửi thông báo tự động:
        // - Khi có phản hồi mới: báo cho tác giả bài viết gốc
        // - Khi học viên đặt "Câu hỏi cho giảng viên": báo ngay cho giảng viên
        if ($parent && $parent->user_id !== $user->id) {
            AppNotification::notifyUsers(
                [$parent->user_id],
                'Có phản hồi mới cho bài viết của bạn',
                "{$user->name} đã trả lời trong bài \"{$lesson->title}\": ".str($interaction->content)->limit(120),
                AppNotification::TYPE_INTERACTION,
                $user->id,
                $course->id,
                route('student.courses.learn', [$course, $lesson]).'#interaction-'.$parent->id
            );
        } elseif (! $parent && $interaction->isQuestion() && $course->teacher_id && $course->teacher_id !== $user->id) {
            AppNotification::notifyUsers(
                [$course->teacher_id],
                "Câu hỏi mới trong khóa \"{$course->title}\"",
                "{$user->name} vừa đặt câu hỏi tại bài \"{$lesson->title}\": ".str($interaction->content)->limit(120),
                AppNotification::TYPE_INTERACTION,
                $user->id,
                $course->id,
                route('interactions.index', ['course_id' => $course->id])
            );
        }

        $subtab = ($parent ? $parent->isQuestion() : $interaction->isQuestion()) ? 'question' : 'discussion';

        return back()
            ->with('status', $parent ? 'Đã gửi phản hồi thành công.' : 'Đã đăng nội dung thành công.')
            ->with('interaction_subtab', $subtab);
    }

    public function reply(Request $request, LessonInteraction $interaction): RedirectResponse
    {
        $this->authorize('update', $interaction->course);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'mark_resolved' => ['nullable', 'boolean'],
        ], [
            'content.required' => 'Vui lòng nhập nội dung phản hồi.',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $root = $interaction->parent_id ? $interaction->parent : $interaction;

        $reply = LessonInteraction::create([
            'course_id' => $root->course_id,
            'lesson_id' => $root->lesson_id,
            'user_id' => $user->id,
            'parent_id' => $root->id,
            'type' => LessonInteraction::TYPE_DISCUSSION,
            'content' => trim($validated['content']),
            'status' => LessonInteraction::STATUS_OPEN,
        ]);

        if ($root->isQuestion() && $request->boolean('mark_resolved', true)) {
            $root->update(['status' => LessonInteraction::STATUS_RESOLVED]);
        }

        if ($root->user_id !== $user->id) {
            $actionUrl = $root->lesson_id
                ? route('student.courses.learn', [$root->course_id, $root->lesson_id]).'#interaction-'.$root->id
                : route('student.courses.show', $root->course_id);

            AppNotification::notifyUsers(
                [$root->user_id],
                'Giảng viên đã phản hồi câu hỏi của bạn',
                "{$user->name} trả lời: ".str($reply->content)->limit(140),
                AppNotification::TYPE_INTERACTION,
                $user->id,
                $root->course_id,
                $actionUrl
            );
        }

        return back()->with('status', 'Đã gửi phản hồi cho học viên.');
    }

    public function updateStatus(Request $request, LessonInteraction $interaction): RedirectResponse
    {
        $this->authorize('update', $interaction->course);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(LessonInteraction::STATUSES))],
        ]);

        $interaction->update(['status' => $validated['status']]);

        return back()->with('status', 'Đã cập nhật trạng thái tương tác.');
    }

    public function togglePin(LessonInteraction $interaction): RedirectResponse
    {
        $this->authorize('update', $interaction->course);

        $interaction->update(['is_pinned' => ! $interaction->is_pinned]);

        return back()->with('status', $interaction->is_pinned ? 'Đã ghim tương tác lên đầu.' : 'Đã bỏ ghim tương tác.');
    }

    public function toggleLike(Request $request, LessonInteraction $interaction): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $course = $interaction->course;
        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists();
        abort_unless($isEnrolled || $user->can('update', $course), 403);

        $alreadyLiked = $interaction->likedByUsers()->where('user_id', $user->id)->exists();

        if ($alreadyLiked) {
            $interaction->likedByUsers()->detach($user->id);
            $liked = false;
        } else {
            $interaction->likedByUsers()->attach($user->id);
            $liked = true;
        }

        $count = $interaction->likedByUsers()->count();
        $interaction->update(['likes_count' => $count]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'liked' => $liked,
                'likes_count' => $count,
            ]);
        }

        return back();
    }

    public function destroy(LessonInteraction $interaction): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($interaction->user_id === $user->id || $user->can('update', $interaction->course), 403);

        $interaction->delete();

        return back()->with('status', 'Đã xóa tương tác.');
    }

    private function parseTimestampSeconds(mixed $timestampInput, mixed $rawSeconds): ?int
    {
        if (is_string($timestampInput) && trim($timestampInput) !== '') {
            $text = trim($timestampInput);
            if (preg_match('/^\d+$/', $text)) {
                return max(0, min(86400, (int) $text));
            }
            if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $text, $m)) {
                $seconds = isset($m[3]) && $m[3] !== ''
                    ? ((int) $m[1] * 3600) + ((int) $m[2] * 60) + (int) $m[3]
                    : ((int) $m[1] * 60) + (int) $m[2];

                return max(0, min(86400, $seconds));
            }
        }

        if ($rawSeconds !== null && $rawSeconds !== '') {
            return max(0, min(86400, (int) $rawSeconds));
        }

        return null;
    }
}
