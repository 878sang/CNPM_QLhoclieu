<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ], [
            'title.required' => 'Vui lòng nhập tên chương.',
            'title.max' => 'Tên chương không được vượt quá 255 ký tự.',
        ]);

        $nextPosition = ((int) $course->chapters()->max('position')) + 1;

        $course->chapters()->create([
            'title' => $validated['title'],
            'position' => $nextPosition,
        ]);

        return redirect()->back()->with('status', 'Đã thêm chương mới thành công.');
    }

    public function update(Request $request, Course $course, Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $course);
        abort_unless($chapter->course_id === $course->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ], [
            'title.required' => 'Vui lòng nhập tên chương.',
            'title.max' => 'Tên chương không được vượt quá 255 ký tự.',
        ]);

        $chapter->update(['title' => $validated['title']]);

        return redirect()->back()->with('status', 'Đã cập nhật chương thành công.');
    }

    public function destroy(Course $course, Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $course);
        abort_unless($chapter->course_id === $course->id, 404);

        $chapter->delete();

        return redirect()->back()->with('status', 'Đã xóa chương thành công.');
    }
}
