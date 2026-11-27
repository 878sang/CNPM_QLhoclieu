<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Document;
use App\Models\Lesson;
use App\Models\SavedItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SavedItemController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $type = (string) $request->query('type', 'all');
        $courseId = $request->filled('course_id') ? $request->integer('course_id') : null;
        $term = trim((string) $request->query('q', ''));

        $items = SavedItem::with([
            'course.subject',
            'lesson.chapter',
            'document.lesson',
        ])
            ->where('user_id', $user->id)
            ->when($type === 'lesson', fn ($q) => $q->whereNotNull('lesson_id')->whereNull('document_id'))
            ->when($type === 'document', fn ($q) => $q->whereNotNull('document_id'))
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->when($term !== '', function ($q) use ($term): void {
                $q->where(function ($sub) use ($term): void {
                    $sub->whereRelation('lesson', 'title', 'like', "%{$term}%")
                        ->orWhereRelation('document', 'title', 'like', "%{$term}%")
                        ->orWhereRelation('course', 'title', 'like', "%{$term}%")
                        ->orWhere('note', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $savedCourses = Course::whereIn(
            'id',
            SavedItem::where('user_id', $user->id)->distinct()->pluck('course_id')
        )->orderBy('title')->get();

        return view('student.saved', [
            'savedItems' => $items,
            'savedCourses' => $savedCourses,
            'selectedType' => $type,
            'selectedCourseId' => $courseId,
            'term' => $term,
        ]);
    }

    public function toggle(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'lesson_id' => ['nullable', 'integer', 'required_without:document_id'],
            'document_id' => ['nullable', 'integer', 'required_without:lesson_id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! empty($validated['document_id'])) {
            $document = Document::with('course')->findOrFail((int) $validated['document_id']);
            $course = $document->course;
            abort_unless($course && $this->canBookmarkCourse($user, $course), 403);

            $existing = SavedItem::where('user_id', $user->id)
                ->where('document_id', $document->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $isSaved = false;
                $message = 'Đã bỏ lưu học liệu.';
            } else {
                SavedItem::create([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'lesson_id' => $document->lesson_id,
                    'document_id' => $document->id,
                    'note' => $validated['note'] ?? null,
                ]);
                $isSaved = true;
                $message = 'Đã lưu học liệu vào mục Đã lưu.';
            }
        } else {
            $lesson = Lesson::with('chapter.course')->findOrFail((int) $validated['lesson_id']);
            $course = $lesson->chapter?->course;
            abort_unless($course && $this->canBookmarkCourse($user, $course), 403);

            $existing = SavedItem::where('user_id', $user->id)
                ->where('lesson_id', $lesson->id)
                ->whereNull('document_id')
                ->first();

            if ($existing) {
                $existing->delete();
                $isSaved = false;
                $message = 'Đã bỏ lưu bài giảng.';
            } else {
                SavedItem::create([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'lesson_id' => $lesson->id,
                    'document_id' => null,
                    'note' => $validated['note'] ?? null,
                ]);
                $isSaved = true;
                $message = 'Đã lưu bài giảng vào mục Đã lưu.';
            }
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'saved' => $isSaved,
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('status', $message);
    }

    public function destroy(SavedItem $savedItem): RedirectResponse
    {
        abort_unless($savedItem->user_id === Auth::id(), 403);
        $savedItem->delete();

        return redirect()->back()->with('status', 'Đã xóa mục khỏi danh sách Đã lưu.');
    }

    private function canBookmarkCourse(User $user, Course $course): bool
    {
        if ($course->status === 'public' || $user->can('update', $course)) {
            return true;
        }

        return $course->enrollments()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }
}
