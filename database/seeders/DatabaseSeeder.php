<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $roles = collect([
            ['name' => 'admin', 'display_name' => 'Quản trị viên'],
            ['name' => 'teacher', 'display_name' => 'Giảng viên'],
            ['name' => 'student', 'display_name' => 'Học viên'],
        ])->mapWithKeys(fn ($role) => [$role['name'] => Role::create($role)]);
        $make = fn (string $role, string $username, string $email, string $name) => User::factory()->create(compact('username', 'email', 'name') + ['role_id' => $roles[$role]->id, 'password' => 'password']);
        $make('admin', 'admin', 'admin@example.com', 'Quản trị viên');
        $teacher = $make('teacher', 'giangvien', 'teacher@example.com', 'Giảng viên mẫu');
        $make('student', 'hocvien1', 'student1@example.com', 'Học viên Một');
        $make('student', 'hocvien2', 'student2@example.com', 'Học viên Hai');
        foreach (['Lập trình Web', 'Cơ sở dữ liệu', 'Công nghệ phần mềm'] as $name) {
            $subject = Subject::create(['name' => $name, 'description' => 'Môn học mẫu']);
            Course::factory()->create(['subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => 'public']);
        }
    }
}
