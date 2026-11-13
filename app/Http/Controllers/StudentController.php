<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\DocumentProgress;
use App\Models\Lesson;
use App\Models\Progress;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function dashboard(): View
    {
        return view('student.dashboard', [
            'courses' => $this->myCoursesQuery()->limit(4)->get(),
        ]);
    }

    public function courses(Request $request): View
    {
        return view('student.courses', [
            'courses' => $this->myCoursesQuery($request)->paginate(12)->withQueryString(),
            'subjects' => Subject::orderBy('name')->get(),
        ]);
    }

    public function progress(Request $request): View
    {
        return view('student.progress', [
            'courses' => $this->myCoursesQuery($request)->get(),
            'subjects' => Subject::orderBy('name')->get(),
        ]);
    }

    public function show(Course $course): View
    {
        /** @var User $user */
        /** @var User $user */
        $user = Auth::user();
        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists();

        abort_unless($isEnrolled || $course->status === 'public' || $user->hasRole(['admin', 'teacher']), 404);

        $course->load([
            'subject',
            'teacher',
            'chapters.lessons' => fn ($query) => $query->where('status', 'published'),
            'chapters.lessons.documents' => fn ($query) => $query->approved(),
        ]);

        $documents = $isEnrolled
            ? $course->documents()->approved()->with('lesson.chapter')->orderBy('title')->get()
            : collect();

        return view('student.course-detail', [
            'course' => $course,
            'isEnrolled' => $isEnrolled,
            'documents' => $documents,
            'progressByDocument' => DocumentProgress::where('user_id', auth()->id())->whereIn('document_id', $documents->pluck('id'))->get()->keyBy('document_id'),
            'progressPercent' => $isEnrolled ? Course::withProgressFor(auth()->user())->find($course->id)->progress_percent : 0,
            'percentage' => $course->progressPercentageFor($user),
            'progressStatus' => $course->progressStatusFor($user),
            'lastLesson' => $course->lastStudiedLessonFor($user),
        ]);
    }

    public function leave(Course $course): RedirectResponse
    {
        $course->enrollments()
            ->where('user_id', Auth::id())
            ->firstOrFail()
            ->delete();

        return redirect()->route('student.courses')->with('status', 'Bạn đã rời khỏi khóa học.');
    }

    public function search(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $courses = Course::with('subject')->where('status', 'public')
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(fn ($query) => $query->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhereRelation('subject', 'name', 'like', "%{$term}%"));
            })
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->input('subject_id')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('student.search', [
            'courses' => $courses,
            'term' => $term,
            'subjects' => Subject::orderBy('name')->get(),
        ]);
    }

    public function learn(Course $course, ?Lesson $lesson = null): View|RedirectResponse
    {
        /** @var User $user */
        /** @var User $user */
        $user = Auth::user();
        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists();

        abort_unless($isEnrolled || $user->can('update', $course), 403, 'Bạn cần tham gia khóa học này để vào học.');

        $chapters = $course->chapters()
            ->with([
                'lessons' => fn ($query) => $query->where('status', 'published'),
                'lessons.documents' => fn ($query) => $query->approved(),
            ])
            ->orderBy('position')
            ->get();

        // Flatten all lessons across chapters to find order
        $allLessons = $chapters->pluck('lessons')->flatten();

        if ($allLessons->isEmpty()) {
            return view('student.learn', [
                'course' => $course,
                'chapters' => $chapters,
                'currentLesson' => null,
                'prevLesson' => null,
                'nextLesson' => null,
                'completedLessonIds' => [],
                'currentProgress' => null,
                'percentage' => 0,
            ]);
        }

        // Determine current lesson
        if (! $lesson) {
            $lesson = $course->lastStudiedLessonFor($user) ?? $allLessons->first();
        } else {
            // Validate that lesson belongs to this course
            abort_unless($allLessons->contains('id', $lesson->id), 404);
        }

        // Find currentIndex, prev and next
        $currentIndex = $allLessons->search(fn ($item) => $item->id === $lesson->id);
        $prevLesson = $currentIndex > 0 ? $allLessons->get($currentIndex - 1) : null;
        $nextLesson = ($currentIndex !== false && $currentIndex < $allLessons->count() - 1)
            ? $allLessons->get($currentIndex + 1)
            : null;

        // Fetch user progress for all lessons in this course
        $progresses = Progress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->get();

        $completedLessonIds = $progresses->where('is_completed', true)->pluck('lesson_id')->all();
        $currentProgress = $progresses->firstWhere('lesson_id', $lesson->id);

        return view('student.learn', [
            'course' => $course,
            'chapters' => $chapters,
            'currentLesson' => $lesson,
            'prevLesson' => $prevLesson,
            'nextLesson' => $nextLesson,
            'completedLessonIds' => $completedLessonIds,
            'currentProgress' => $currentProgress,
            'percentage' => $course->progressPercentageFor($user),
        ]);
    }

    public function toggleLessonProgress(Request $request, Course $course, Lesson $lesson): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists();

        abort_unless($isEnrolled || $user->can('update', $course), 403);
        abort_unless($lesson->chapter && $lesson->chapter->course_id === $course->id, 404);

        $progress = Progress::firstOrNew([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
        ]);

        $progress->is_completed = ! $progress->is_completed;
        $progress->completed_at = $progress->is_completed ? now() : null;
        $progress->save();

        $percentage = $course->progressPercentageFor($user);
        $status = $course->progressStatusFor($user);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_completed' => $progress->is_completed,
                'percentage' => $percentage,
                'status' => $status,
                'status_label' => $course->progressStatusLabel($user),
            ]);
        }

        $message = $progress->is_completed ? 'Đã đánh dấu hoàn thành bài học.' : 'Đã bỏ đánh dấu hoàn thành bài học.';

        return redirect()->back()->with('status', $message);
    }

    public function updateVideoPosition(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists();

        abort_unless($isEnrolled || $user->can('update', $course), 403);
        abort_unless($lesson->chapter && $lesson->chapter->course_id === $course->id, 404);

        $seconds = max(0, min(86400, (int) $request->input('seconds', 0)));
        $duration = max(0, min(86400, (int) $request->input('duration', 0)));
        $isEnded = filter_var($request->input('is_ended', false), FILTER_VALIDATE_BOOLEAN);

        $progress = Progress::firstOrNew([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
        ]);

        // Tiến độ video luôn lấy thời lượng xem lớn nhất, không bị tụt khi tua lùi ôn lại bài
        $progress->last_position_seconds = max((int) $progress->last_position_seconds, $seconds);
        if ($duration > 0) {
            $progress->duration_seconds = max((int) $progress->duration_seconds, $duration);
        }

        // Chỉ hoàn thành bài học khi xem hết video (khi video kết thúc hoặc xem đến cuối thời lượng)
        if ($isEnded || ($progress->duration_seconds > 0 && $progress->last_position_seconds >= ($progress->duration_seconds - 2))) {
            $progress->is_completed = true;
            $progress->completed_at = $progress->completed_at ?: now();
        }

        $progress->save();

        return response()->json([
            'success' => true,
            'last_position_seconds' => $progress->last_position_seconds,
            'duration_seconds' => $progress->duration_seconds,
            'watched_percentage' => $progress->watchedPercentage(),
            'is_completed' => (bool) $progress->is_completed,
            'percentage' => $course->progressPercentageFor($user),
            'status' => $course->progressStatusFor($user),
            'status_label' => $course->progressStatusLabel($user),
        ]);
    }

    private function myCoursesQuery(?Request $request = null)
    {
        $userId = Auth::id();

        return Course::with([
            'subject',
            'teacher',
            'enrollments' => fn ($query) => $query->where('user_id', $userId),
            'chapters.lessons' => fn ($query) => $query->where('status', 'published'),
            'progresses' => fn ($query) => $query->where('user_id', $userId),
        ])
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', auth()->id()))
            ->withProgressFor(auth()->user())
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', $userId))
            ->when($request?->filled('q'), function ($query) use ($request): void {
                $term = trim((string) $request->input('q'));
                $query->where(function ($query) use ($term): void {
                    $query->where('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhereRelation('subject', 'name', 'like', "%{$term}%");
                });
            })
            ->when($request?->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->input('subject_id')))
            ->latest();
    }
}
