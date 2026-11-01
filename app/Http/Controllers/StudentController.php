<?php

namespace App\Http\Controllers;

use App\Models\Course;
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
        return view('student.courses', ['courses' => $this->myCoursesQuery()->paginate(12)]);
    }

    public function progress(): View
    {
        return view('student.progress', ['courses' => $this->myCoursesQuery()->get()]);
    }

    public function search(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $courses = Course::with('subject')->where('status', 'public')
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(fn ($query) => $query->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")->orWhereRelation('subject', 'name', 'like', "%{$term}%"));
            })->latest()->paginate(12)->withQueryString();

        return view('student.search', compact('courses', 'term'));
    }

    private function myCoursesQuery()
    {
        return Course::with('subject')->whereHas('enrollments', fn ($query) => $query->where('user_id', auth()->id()))->latest();
    }
}
