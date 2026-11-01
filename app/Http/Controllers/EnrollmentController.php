<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function index(Course $course): View
    {
        $this->authorize('update', $course);

        return view('courses.students', [
            'course' => $course,
            'enrollments' => $course->enrollments()->with('user')->latest()->paginate(10),
            'availableStudents' => User::whereRelation('role', 'name', 'student')
                ->where('status', 'active')
                ->whereDoesntHave('enrollments', fn ($query) => $query->where('course_id', $course->id))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreEnrollmentRequest $request, Course $course): RedirectResponse
    {
        $course->enrollments()->create([...$request->validated(), 'status' => 'active']);

        return redirect()->route('courses.students.index', $course)->with('status', 'Đã thêm học viên vào khóa học.');
    }

    public function destroy(Course $course, Enrollment $enrollment): RedirectResponse
    {
        $this->authorize('update', $course);

        $enrollment->delete();

        return redirect()->route('courses.students.index', $course)->with('status', 'Đã xóa học viên khỏi khóa học.');
    }
}
