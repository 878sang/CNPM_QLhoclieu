<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    /**
     * Kéo thả sắp xếp thứ tự bài học (hỗ trợ sắp xếp trong 1 chương hoặc kéo bài học sang chương khác).
     * Không thay đổi trạng thái duyệt (published/pending/draft) của bài học.
     */
    public function reorder(Request $request, Course $course): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $course);

        $validated = $request->validate([
            'chapter_id' => ['nullable', 'integer'],
            'lesson_ids' => ['nullable', 'array'],
            'lesson_ids.*' => ['integer'],
            'chapters' => ['nullable', 'array'],
            'chapters.*.chapter_id' => ['required_with:chapters', 'integer'],
            'chapters.*.lesson_ids' => ['present', 'array'],
            'chapters.*.lesson_ids.*' => ['integer'],
        ]);

        $validChapterIds = $course->chapters()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validLessonIds = $course->lessons()->pluck('lessons.id')->map(fn ($id) => (int) $id)->all();

        $groups = [];
        if (! empty($validated['chapters'])) {
            foreach ($validated['chapters'] as $group) {
                $cid = (int) $group['chapter_id'];
                if (in_array($cid, $validChapterIds, true)) {
                    $groups[$cid] = array_values(array_intersect(
                        array_unique(array_map('intval', $group['lesson_ids'] ?? [])),
                        $validLessonIds
                    ));
                }
            }
        } elseif (! empty($validated['chapter_id']) && isset($validated['lesson_ids'])) {
            $cid = (int) $validated['chapter_id'];
            abort_unless(in_array($cid, $validChapterIds, true), 404);
            $groups[$cid] = array_values(array_intersect(
                array_unique(array_map('intval', $validated['lesson_ids'])),
                $validLessonIds
            ));
        } else {
            abort(422, 'Dữ liệu sắp xếp bài học không hợp lệ.');
        }

        DB::transaction(function () use ($groups): void {
            // Đảm bảo một bài học chỉ xuất hiện ở chương cuối cùng được chỉ định nếu gửi trùng
            $assignedLessons = [];
            foreach ($groups as $chapterId => $lessonIds) {
                $pos = 1;
                foreach ($lessonIds as $lessonId) {
                    if (isset($assignedLessons[$lessonId])) {
                        continue;
                    }
                    $assignedLessons[$lessonId] = true;

                    Lesson::where('id', $lessonId)->update([
                        'chapter_id' => $chapterId,
                        'position' => $pos++,
                    ]);
                }
            }
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật thứ tự bài giảng.',
            ]);
        }

        return redirect()->back()->with('status', 'Đã cập nhật thứ tự bài giảng.');
    }

    /**
     * Di chuyển bài học lên/xuống trong chương hoặc chuyển sang một chương khác của khóa học.
     */
    public function move(Request $request, Course $course, Chapter $chapter, Lesson $lesson): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $course);
        abort_unless($chapter->course_id === $course->id, 404);
        abort_unless($lesson->chapter_id === $chapter->id, 404);

        $validated = $request->validate([
            'direction' => ['nullable', 'string', 'in:up,down'],
            'target_chapter_id' => ['nullable', 'integer'],
        ]);

        if (! empty($validated['target_chapter_id']) && (int) $validated['target_chapter_id'] !== $chapter->id) {
            $targetChapter = $course->chapters()->findOrFail((int) $validated['target_chapter_id']);

            DB::transaction(function () use ($chapter, $targetChapter, $lesson): void {
                $nextPos = ((int) $targetChapter->lessons()->max('position')) + 1;
                $lesson->update([
                    'chapter_id' => $targetChapter->id,
                    'position' => $nextPos,
                ]);

                // Chuẩn hóa lại thứ tự chương cũ
                $remaining = $chapter->lessons()->orderBy('position')->orderBy('id')->get()->values();
                foreach ($remaining as $idx => $item) {
                    if ((int) $item->position !== $idx + 1) {
                        $item->update(['position' => $idx + 1]);
                    }
                }
            });

            $msg = "Đã chuyển bài giảng sang chương \"{$targetChapter->title}\".";
        } else {
            $direction = $validated['direction'] ?? 'down';
            $lessons = $chapter->lessons()
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->values();

            $currentIndex = $lessons->search(fn (Lesson $item) => $item->id === $lesson->id);
            if ($currentIndex !== false) {
                $swapIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
                if ($swapIndex >= 0 && $swapIndex < $lessons->count()) {
                    $temp = $lessons[$currentIndex];
                    $lessons[$currentIndex] = $lessons[$swapIndex];
                    $lessons[$swapIndex] = $temp;
                }

                DB::transaction(function () use ($lessons): void {
                    foreach ($lessons->values() as $idx => $les) {
                        if ((int) $les->position !== $idx + 1) {
                            $les->update(['position' => $idx + 1]);
                        }
                    }
                });
            }

            $msg = 'Đã di chuyển vị trí bài giảng.';
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return redirect()->route('courses.show', [
            'course' => $course,
            'lesson_id' => $lesson->id,
            'tab' => 'content',
        ])->with('status', $msg);
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
