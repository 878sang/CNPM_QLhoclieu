<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function students(): View
    {
        $students = User::with(['enrollments.course'])
            ->whereRelation('role', 'name', 'student')
            ->whereHas('enrollments.course', fn ($query) => $query->where('teacher_id', auth()->id()))
            ->orderBy('name')
            ->paginate(15);

        $students->getCollection()->transform(function (User $student): User {
            $student->setAttribute(
                'teacher_course_count',
                $student->enrollments
                    ->filter(fn ($enrollment) => $enrollment->course?->teacher_id === auth()->id())
                    ->count(),
            );
            $student->setAttribute(
                'teacher_course_names',
                $student->enrollments
                    ->filter(fn ($enrollment) => $enrollment->course?->teacher_id === auth()->id())
                    ->pluck('course.title')
                    ->filter()
                    ->implode(', '),
            );

            return $student;
        });

        return view('teacher.students', compact('students'));
    }
}
