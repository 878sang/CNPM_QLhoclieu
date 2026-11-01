<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Course> */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return ['subject_id' => Subject::factory(), 'teacher_id' => User::factory(), 'title' => fake()->sentence(4), 'description' => fake()->paragraph(), 'status' => 'draft'];
    }
}
