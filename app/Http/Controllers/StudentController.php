<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function dashboard(): View
    {
        return view('student.dashboard', ['courses' => $this->myCoursesQuery()->limit(4)->get()]);
    }

    public function courses(): View
    {
        return view('student.courses', [
            'courses' => $this->myCoursesQuery(request())->paginate(12)->withQueryString(),
            'subjects' => Subject::orderBy('name')->get(),
        ]);
    }

    public function progress(): View
    {
        return view('student.progress', [
            'courses' => $this->myCoursesQuery(request())->get(),
            'subjects' => Subject::orderBy('name')->get(),
        ]);
    }

    public function show(Course $course): View
    {
        $isEnrolled = $course->enrollments()->where('user_id', auth()->id())->exists();
        abort_unless($isEnrolled || $course->status === 'public', 404);

        return view('student.course-detail', [
            'course' => $course->load(['subject', 'teacher']),
            'isEnrolled' => $isEnrolled,
            'documents' => $isEnrolled
                ? $course->documents()->approved()->with('lesson.chapter')->orderBy('title')->get()
                : collect(),
        ]);
    }

    public function leave(Course $course): RedirectResponse
    {
        $course->enrollments()
            ->where('user_id', auth()->id())
            ->firstOrFail()
            ->delete();

        return redirect()->route('student.courses')->with('status', 'Bạn đã rời khỏi khóa học.');
    }

    public function search(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $courses = Course::with('subject')->where('status', 'public')
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(fn ($query) => $query->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")->orWhereRelation('subject', 'name', 'like', "%{$term}%"));
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

    private function myCoursesQuery(?Request $request = null)
    {
        return Course::with([
            'subject',
            'enrollments' => fn ($query) => $query->where('user_id', auth()->id()),
        ])
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', auth()->id()))
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
