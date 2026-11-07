<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $role = $user->role?->name;

        $courseList = match ($role) {
            'student' => $user->enrollments()->with('course.subject')->latest()->get()->pluck('course')->filter(),
            'teacher' => $user->coursesTaught()->with('subject')->latest()->get(),
            default => Course::with('subject')->latest()->limit(6)->get(),
        };

        return view('dashboard', [
            'courseCount' => $this->courseCount($role, $user),
            'courseHeading' => $role === 'student' ? 'Khóa học của tôi' : 'Khóa học mới nhất',
            'courseLabel' => $role === 'student' ? 'Khóa học đang tham gia' : 'Khóa học đang quản lý',
            'courseList' => $courseList->take(4),
            'subjectCount' => Subject::count(),
            'workspaceRoute' => match ($role) {
                'admin' => 'admin.dashboard',
                'teacher' => 'teacher.dashboard',
                default => 'student.dashboard',
            },
        ]);
    }

    private function courseCount(?string $role, User $user): int
    {
        return match ($role) {
            'student' => $user->enrollments()->count(),
            'teacher' => $user->coursesTaught()->count(),
            default => Course::count(),
        };
    }
}
