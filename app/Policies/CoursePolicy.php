<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Course $course): bool
    {
        return $course->status === 'public' || $user->hasRole('admin') || $course->teacher_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'teacher']);
    }

    public function update(User $user, Course $course): bool
    {
        return $user->hasRole('admin') || ($user->hasRole('teacher') && $course->teacher_id === $user->id);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }
}
