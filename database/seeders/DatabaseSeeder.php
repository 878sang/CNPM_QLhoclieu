<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\MaterialLog;
use App\Models\Progress;
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
        $student1 = $make('student', 'hocvien1', 'student1@example.com', 'Học viên Một');
        $student2 = $make('student', 'hocvien2', 'student2@example.com', 'Học viên Hai');

        for ($i = 3; $i <= 20; $i++) {
            $make(
                'student',
                "hocvien{$i}",
                "student{$i}@example.com",
                "Hoc vien {$i}",
            );
        }

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

        $createdCourses = [];

        foreach ($subjects as $subjectData) {
            $subject = Subject::updateOrCreate(['name' => $subjectData['name']], ['description' => $subjectData['description']]);
            $course = Course::updateOrCreate([
                'subject_id' => $subject->id,
                'title' => "Ôn thi THPT môn {$subject->name}",
            ], [
                'teacher_id' => $teacher->id,
                'description' => "Khóa học tổng hợp kiến thức trọng tâm, học liệu và đề luyện tập môn {$subject->name}.",
                'start_at' => now()->subDays(7),
                'end_at' => now()->addMonths(3),
                'status' => 'public',
            ]);

            $createdCourses[$subjectData['name']] = $course;
        }

        // Demo data cho Sprint 2 (US-03, US-04) và Sprint 3 (US-05, US-06) trên khóa Toán
        $mathCourse = $createdCourses['Toán'] ?? null;
        if ($mathCourse) {
            // Chương 1
            $ch1 = Chapter::firstOrCreate([
                'course_id' => $mathCourse->id,
                'title' => 'Chương 1: Ứng dụng đạo hàm để khảo sát hàm số',
            ], [
                'position' => 1,
            ]);

            $les1 = Lesson::firstOrCreate([
                'chapter_id' => $ch1->id,
                'title' => 'Bài 1: Tính đơn điệu của hàm số',
            ], [
                'position' => 1,
            ]);

            $les2 = Lesson::firstOrCreate([
                'chapter_id' => $ch1->id,
                'title' => 'Bài 2: Cực trị của hàm số',
            ], [
                'position' => 2,
            ]);

            $les3 = Lesson::firstOrCreate([
                'chapter_id' => $ch1->id,
                'title' => 'Bài 3: Giá trị lớn nhất và nhỏ nhất của hàm số',
            ], [
                'position' => 3,
            ]);

            // Chương 2
            $ch2 = Chapter::firstOrCreate([
                'course_id' => $mathCourse->id,
                'title' => 'Chương 2: Hàm số lũy thừa, hàm số mũ và logarit',
            ], [
                'position' => 2,
            ]);

            $les4 = Lesson::firstOrCreate([
                'chapter_id' => $ch2->id,
                'title' => 'Bài 1: Lũy thừa và hàm số lũy thừa',
            ], [
                'position' => 1,
            ]);

            $les5 = Lesson::firstOrCreate([
                'chapter_id' => $ch2->id,
                'title' => 'Bài 2: Hàm số logarit và phương trình logarit',
            ], [
                'position' => 2,
            ]);

            // Tài liệu & học liệu mẫu (TS2-04, TS2-05)
            // 1. Tệp PDF đề cương (có sẵn trong storage)
            Document::firstOrCreate([
                'course_id' => $mathCourse->id,
                'title' => 'Đề cương tóm tắt lý thuyết khảo sát hàm số (PDF)',
            ], [
                'lesson_id' => $les1->id,
                'type' => 'file',
                'file_path' => 'documents/demo-de-cuong-toan.pdf',
                'original_name' => 'De-cuong-khao-sat-ham-so.pdf',
                'mime_type' => 'application/pdf',
                'size' => 1024,
                'allow_view' => true,
                'allow_download' => true,
            ]);

            // 2. Video bài giảng YouTube
            $videoDoc = Document::firstOrCreate([
                'course_id' => $mathCourse->id,
                'title' => 'Video bài giảng: Chinh phục cực trị hàm số',
            ], [
                'lesson_id' => $les2->id,
                'type' => 'video',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'allow_view' => true,
                'allow_download' => false,
            ]);

            // 3. Liên kết ngoài
            Document::firstOrCreate([
                'course_id' => $mathCourse->id,
                'title' => 'Trang tra cứu công thức giải nhanh Toán học',
            ], [
                'lesson_id' => $les3->id,
                'type' => 'link',
                'url' => 'https://moet.gov.vn',
                'allow_view' => true,
                'allow_download' => false,
            ]);

            // Ghi danh học viên (US-05)
            Enrollment::firstOrCreate([
                'course_id' => $mathCourse->id,
                'user_id' => $student1->id,
            ], [
                'status' => 'active',
            ]);

            Enrollment::firstOrCreate([
                'course_id' => $mathCourse->id,
                'user_id' => $student2->id,
            ], [
                'status' => 'active',
            ]);

            // Thêm ghi danh vào môn Tiếng Anh và Văn
            if (isset($createdCourses['Tiếng Anh'])) {
                Enrollment::firstOrCreate([
                    'course_id' => $createdCourses['Tiếng Anh']->id,
                    'user_id' => $student1->id,
                ], ['status' => 'active']);
            }

            // Tiến độ mẫu cho hocvien1 (US-06, TS3-07, TS3-08)
            // Hoàn thành Bài 1
            Progress::updateOrCreate([
                'user_id' => $student1->id,
                'course_id' => $mathCourse->id,
                'lesson_id' => $les1->id,
            ], [
                'is_completed' => true,
                'last_position_seconds' => 0,
                'completed_at' => now()->subDay(),
            ]);

            // Đang học dở Bài 2 ở giây thứ 145
            Progress::updateOrCreate([
                'user_id' => $student1->id,
                'course_id' => $mathCourse->id,
                'lesson_id' => $les2->id,
            ], [
                'is_completed' => false,
                'last_position_seconds' => 145,
                'completed_at' => null,
            ]);

            // Lịch sử xem tài liệu mẫu (TS3-01, TS3-03)
            MaterialLog::firstOrCreate([
                'user_id' => $student1->id,
                'course_id' => $mathCourse->id,
                'document_id' => $videoDoc->id,
                'action' => 'view',
            ], [
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 Demo Browser',
            ]);
        }
    }
}
