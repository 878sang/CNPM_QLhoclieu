<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LessonController extends Controller
{
    public function store(Request $request, Course $course, Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $course);
        abort_unless($chapter->course_id === $course->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ], [
            'title.required' => 'Vui lòng nhập tên bài học.',
            'title.max' => 'Tên bài học không được vượt quá 255 ký tự.',
        ]);

        $nextPosition = ((int) $chapter->lessons()->max('position')) + 1;

        $chapter->lessons()->create([
            'title' => $validated['title'],
            'position' => $nextPosition,
            'status' => 'draft',
        ]);

        return redirect()->back()->with('status', 'Đã thêm bài học mới thành công.');
    }

    public function update(Request $request, Course $course, Chapter $chapter, Lesson $lesson): RedirectResponse
    {
        $this->authorize('update', $course);
        abort_unless($chapter->course_id === $course->id, 404);
        abort_unless($lesson->chapter_id === $chapter->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:draft,pending,published,rejected'],
        ], [
            'title.required' => 'Vui lòng nhập tên bài học.',
            'title.max' => 'Tên bài học không được vượt quá 255 ký tự.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ]);

        $updateData = ['title' => $validated['title']];
        if ($course->teacher_id === Auth::id()) {
            // Every teacher edit is a new version that must be reviewed again.
            $updateData['status'] = 'pending';
        } elseif (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
        }

        $wasPublished = $lesson->status === 'published';
        $lesson->update($updateData);

        if (! $wasPublished && $lesson->status === 'published') {
            AppNotification::notifyCourseStudents(
                $course,
                "Bài giảng mới trong khóa \"{$course->title}\"",
                "Bài giảng \"{$lesson->title}\" vừa được xuất bản. Hãy vào học ngay!",
                AppNotification::TYPE_COURSE,
                Auth::id(),
                route('student.courses.learn', [$course, $lesson])
            );
        }

        return redirect()->back()->with('status', 'Đã cập nhật bài học thành công.');
    }

    public function approve(Lesson $lesson): RedirectResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $lesson->load(['documents', 'chapter.course']);
        $wasPublished = $lesson->status === 'published';
        $lesson->update(['status' => 'published']);
        $lesson->documents()->where('status', 'pending')->update([
            'status' => 'approved',
            'approval_status' => 'approved',
            'rejection_reason' => null,
        ]);

        $course = $lesson->chapter?->course;
        if ($course) {
            if ($course->teacher_id) {
                AppNotification::notifyUsers(
                    [$course->teacher_id],
                    'Bài giảng đã được phê duyệt',
                    "Bài giảng \"{$lesson->title}\" trong khóa \"{$course->title}\" đã được Quản trị viên phê duyệt và xuất bản.",
                    AppNotification::TYPE_APPROVAL,
                    Auth::id(),
                    $course->id,
                    route('courses.show', ['course' => $course->id, 'lesson_id' => $lesson->id, 'tab' => 'content'])
                );
            }

            if (! $wasPublished) {
                AppNotification::notifyCourseStudents(
                    $course,
                    "Bài giảng mới trong khóa \"{$course->title}\"",
                    "Bài giảng \"{$lesson->title}\" đã sẵn sàng. Bấm để vào học ngay!",
                    AppNotification::TYPE_COURSE,
                    $course->teacher_id ?: Auth::id(),
                    route('student.courses.learn', [$course, $lesson])
                );
            }
        }

        return back()->with('status', "Đã duyệt bài giảng \"{$lesson->title}\".");
    }

    public function reject(Request $request, Lesson $lesson): RedirectResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $reason = trim((string) $request->input('reason', 'Nội dung chưa phù hợp.'));
        $lesson->load('chapter.course');
        $lesson->update(['status' => 'rejected']);
        $lesson->documents()->where('status', 'pending')->update([
            'status' => 'rejected',
            'approval_status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        $course = $lesson->chapter?->course;
        if ($course?->teacher_id) {
            AppNotification::notifyUsers(
                [$course->teacher_id],
                'Bài giảng bị từ chối phê duyệt',
                "Bài giảng \"{$lesson->title}\" bị từ chối. Lý do: {$reason}",
                AppNotification::TYPE_APPROVAL,
                Auth::id(),
                $course->id,
                route('courses.show', ['course' => $course->id, 'lesson_id' => $lesson->id, 'tab' => 'content'])
            );
        }

        return back()->with('status', "Đã từ chối bài giảng \"{$lesson->title}\".");
    }

    public function destroy(Course $course, Chapter $chapter, Lesson $lesson): RedirectResponse
    {
        $this->authorize('update', $course);
        abort_unless($chapter->course_id === $course->id, 404);
        abort_unless($lesson->chapter_id === $chapter->id, 404);

        $lesson->delete();

        return redirect()->back()->with('status', 'Đã xóa bài học thành công.');
    }
}
