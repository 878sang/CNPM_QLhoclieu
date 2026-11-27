<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /**
     * Kéo thả sắp xếp lại danh sách các chương trong khóa học.
     */
    public function reorder(Request $request, Course $course): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $course);

        $validated = $request->validate([
            'chapter_ids' => ['required', 'array', 'min:1'],
            'chapter_ids.*' => ['integer'],
        ]);

        $existingIds = $course->chapters()
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $requestedIds = array_values(array_unique(array_map('intval', $validated['chapter_ids'])));
        $orderedIds = array_values(array_intersect($requestedIds, $existingIds));

        foreach ($existingIds as $id) {
            if (! in_array($id, $orderedIds, true)) {
                $orderedIds[] = $id;
            }
        }

        DB::transaction(function () use ($course, $orderedIds): void {
            foreach ($orderedIds as $index => $chapterId) {
                Chapter::where('course_id', $course->id)
                    ->where('id', $chapterId)
                    ->update(['position' => $index + 1]);
            }
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật thứ tự chương học.',
                'chapter_ids' => $orderedIds,
            ]);
        }

        return redirect()->back()->with('status', 'Đã cập nhật thứ tự chương học.');
    }

    /**
     * Di chuyển chương lên hoặc xuống 1 bậc.
     */
    public function move(Request $request, Course $course, Chapter $chapter): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $course);
        abort_unless($chapter->course_id === $course->id, 404);

        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:up,down'],
        ]);

        $chapters = $course->chapters()
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->values();

        $currentIndex = $chapters->search(fn (Chapter $item) => $item->id === $chapter->id);
        if ($currentIndex !== false) {
            $swapIndex = $validated['direction'] === 'up' ? $currentIndex - 1 : $currentIndex + 1;
            if ($swapIndex >= 0 && $swapIndex < $chapters->count()) {
                $temp = $chapters[$currentIndex];
                $chapters[$currentIndex] = $chapters[$swapIndex];
                $chapters[$swapIndex] = $temp;
            }

            DB::transaction(function () use ($chapters): void {
                foreach ($chapters->values() as $idx => $ch) {
                    if ((int) $ch->position !== $idx + 1) {
                        $ch->update(['position' => $idx + 1]);
                    }
                }
            });
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã di chuyển vị trí chương.',
            ]);
        }

        return redirect()->back()->with('status', 'Đã di chuyển vị trí chương.');
    }

    public function destroy(Course $course, Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $course);
        abort_unless($chapter->course_id === $course->id, 404);

        $chapter->delete();

        return redirect()->back()->with('status', 'Đã xóa chương thành công.');
    }
}
