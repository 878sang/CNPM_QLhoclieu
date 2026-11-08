<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
            'status' => ['nullable', 'string', 'in:draft,published'],
        ], [
            'title.required' => 'Vui lòng nhập tên bài học.',
            'title.max' => 'Tên bài học không được vượt quá 255 ký tự.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ]);

        $updateData = ['title' => $validated['title']];
        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
        }

        $lesson->update($updateData);

        return redirect()->back()->with('status', 'Đã cập nhật bài học thành công.');
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
