<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonInteraction;
use App\Models\User;
use Illuminate\Database\Seeder;

class Sprint4DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'staging'])) {
            throw new \RuntimeException('Dữ liệu demo chỉ được tạo trên local hoặc staging.');
        }

        $this->call(DatabaseSeeder::class);
        $lesson = Lesson::orderBy('id')->firstOrFail();
        $lesson->update(['status' => 'published']);
        $course = $lesson->chapter->course;
        $student = User::where('username', 'hocvien1')->firstOrFail();
        Enrollment::updateOrCreate(['course_id' => $course->id, 'user_id' => $student->id], ['status' => 'active']);
        $root = LessonInteraction::firstOrCreate([
            'course_id' => $course->id, 'lesson_id' => $lesson->id,
            'user_id' => $student->id, 'content' => 'Sprint 4: Em muốn hỏi cách xác định khoảng đồng biến của hàm số.',
        ], ['type' => 'discussion', 'is_pinned' => true]);
        LessonInteraction::firstOrCreate([
            'course_id' => $course->id, 'lesson_id' => $lesson->id,
            'user_id' => $course->teacher_id, 'parent_id' => $root->id,
            'content' => 'Em xét dấu đạo hàm trên từng khoảng. Khoảng có đạo hàm dương là khoảng đồng biến nhé.',
        ], ['type' => 'discussion']);
    }
}
