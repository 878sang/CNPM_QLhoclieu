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
        ])->mapWithKeys(fn ($role) => [$role['name'] => Role::firstOrCreate(['name' => $role['name']], ['display_name' => $role['display_name']])]);
        $make = fn (string $role, string $username, string $email, string $name) => User::firstOrCreate(
            ['username' => $username],
            compact('username', 'email', 'name') + ['role_id' => $roles[$role]->id, 'password' => 'password'],
        );
        $make('admin', 'admin', 'admin@example.com', 'Quản trị viên');
        $teacher = $make('teacher', 'giangvien', 'teacher@example.com', 'Giảng viên mẫu');
        $make('student', 'hocvien1', 'student1@example.com', 'Học viên Một');
        $make('student', 'hocvien2', 'student2@example.com', 'Học viên Hai');
        $subjects = [
            ['name' => 'Toán', 'description' => 'Ôn tập kiến thức và luyện đề Toán THPT.'],
            ['name' => 'Ngữ văn', 'description' => 'Ôn đọc hiểu, nghị luận xã hội và nghị luận văn học THPT.'],
            ['name' => 'Tiếng Anh', 'description' => 'Ôn ngữ pháp, từ vựng và kỹ năng làm bài Tiếng Anh THPT.'],
            ['name' => 'Vật lý', 'description' => 'Ôn tập kiến thức và bài tập Vật lý THPT.'],
            ['name' => 'Hóa học', 'description' => 'Ôn tập kiến thức và bài tập Hóa học THPT.'],
            ['name' => 'Sinh học', 'description' => 'Ôn tập kiến thức và luyện đề Sinh học THPT.'],
            ['name' => 'Lịch sử', 'description' => 'Ôn kiến thức trọng tâm và luyện đề Lịch sử THPT.'],
            ['name' => 'Địa lý', 'description' => 'Ôn kiến thức, kỹ năng đọc biểu đồ và luyện đề Địa lý THPT.'],
            ['name' => 'Giáo dục kinh tế và pháp luật', 'description' => 'Ôn tập kiến thức Giáo dục kinh tế và pháp luật THPT.'],
        ];

        foreach ($subjects as $subjectData) {
            $subject = Subject::updateOrCreate(['name' => $subjectData['name']], ['description' => $subjectData['description']]);
            Course::firstOrCreate([
                'subject_id' => $subject->id,
                'title' => "Ôn thi THPT môn {$subject->name}",
            ], [
                'teacher_id' => $teacher->id,
                'description' => "Khóa học tổng hợp kiến thức trọng tâm, học liệu và đề luyện tập môn {$subject->name}.",
                'status' => 'public',
            ]);
        }
    }
}
