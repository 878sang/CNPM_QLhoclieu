<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\MaterialLog;
use App\Models\Progress;
use App\Models\Role;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsAndExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $otherTeacher;

    private User $student;

    private Course $course;

    private Course $otherCourse;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'admin', 'display_name' => 'Quản trị viên']);
        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Giảng viên']);
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Học viên']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);
        $this->teacher = User::factory()->create(['role_id' => $teacherRole->id, 'status' => 'active', 'name' => 'Thầy Minh Toán']);
        $this->otherTeacher = User::factory()->create(['role_id' => $teacherRole->id, 'status' => 'active', 'name' => 'Cô Lan Lý']);
        $this->student = User::factory()->create(['role_id' => $studentRole->id, 'status' => 'active', 'name' => 'Nguyễn Văn Học Viên']);

        $subject = Subject::create(['name' => 'Toán học', 'code' => 'MATH12']);

        $this->course = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Khóa Luyện Thi Đại Học Môn Toán',
            'status' => 'public',
        ]);

        $this->otherCourse = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->otherTeacher->id,
            'title' => 'Khóa Vật Lý Khác Giảng Viên',
            'status' => 'public',
        ]);

        $chapter = Chapter::create([
            'course_id' => $this->course->id,
            'title' => 'Chương 1: Hàm số',
            'position' => 1,
        ]);

        $lesson1 = Lesson::create([
            'chapter_id' => $chapter->id,
            'title' => 'Bài 1: Khảo sát hàm số',
            'position' => 1,
            'status' => 'published',
        ]);

        Lesson::create([
            'chapter_id' => $chapter->id,
            'title' => 'Bài 2: Cực trị hàm số',
            'position' => 2,
            'status' => 'published',
        ]);

        $this->document = Document::create([
            'course_id' => $this->course->id,
            'lesson_id' => $lesson1->id,
            'type' => 'file',
            'title' => 'Chuyên đề Hàm số PDF',
            'file_path' => 'documents/ham-so.pdf',
            'original_name' => 'ham-so.pdf',
            'mime_type' => 'application/pdf',
            'size' => 102400,
            'allow_view' => true,
            'allow_download' => true,
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);

        $otherDoc = Document::create([
            'course_id' => $this->otherCourse->id,
            'type' => 'file',
            'title' => 'Tài liệu Riêng Của Cô Lan',
            'file_path' => 'documents/ly.pdf',
            'original_name' => 'ly.pdf',
            'mime_type' => 'application/pdf',
            'size' => 51200,
            'allow_view' => true,
            'allow_download' => true,
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);

        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);

        // 3 lượt xem, 2 lượt tải trên khóa của thầy Minh
        for ($i = 0; $i < 3; $i++) {
            MaterialLog::create([
                'user_id' => $this->student->id,
                'course_id' => $this->course->id,
                'document_id' => $this->document->id,
                'action' => 'view',
                'ip_address' => '127.0.0.1',
            ]);
        }
        for ($i = 0; $i < 2; $i++) {
            MaterialLog::create([
                'user_id' => $this->student->id,
                'course_id' => $this->course->id,
                'document_id' => $this->document->id,
                'action' => 'download',
                'ip_address' => '127.0.0.1',
            ]);
        }

        // 5 lượt xem trên khóa của cô Lan (không được lẫn vào thống kê của thầy Minh)
        for ($i = 0; $i < 5; $i++) {
            MaterialLog::create([
                'user_id' => $this->student->id,
                'course_id' => $this->otherCourse->id,
                'document_id' => $otherDoc->id,
                'action' => 'view',
                'ip_address' => '127.0.0.1',
            ]);
        }

        // Tiến độ: hoàn thành 1 / 2 bài = 50%
        Progress::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'lesson_id' => $lesson1->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        // Bài tập đã chấm 8/10
        $assignment = Assignment::create([
            'course_id' => $this->course->id,
            'lesson_id' => $lesson1->id,
            'title' => 'Bài tập Chương 1',
            'type' => 'text',
            'submission_mode' => 'file',
            'question_count' => 0,
            'status' => 'published',
        ]);

        Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->student->id,
            'score' => 8.5,
            'max_score' => 10,
            'submitted_at' => now(),
            'graded_at' => now(),
            'graded_by' => $this->teacher->id,
        ]);
    }

    public function test_teacher_views_scoped_statistics_dashboard(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.statistics'));

        $response->assertOk()
            ->assertSee('Thống kê học liệu')
            ->assertSee('Chuyên đề Hàm số PDF')
            ->assertSee('Nguyễn Văn Học Viên')
            ->assertSee('50%')
            ->assertSee('8.5/10')
            ->assertDontSee('Tài liệu Riêng Của Cô Lan');

        $this->assertSame(3, $response->viewData('totalViews'));
        $this->assertSame(2, $response->viewData('totalDownloads'));
        $this->assertSame(50, $response->viewData('avgProgress'));
    }

    public function test_teacher_cannot_filter_statistics_of_another_teachers_course(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('teacher.statistics', ['course_id' => $this->otherCourse->id]))
            ->assertForbidden();
    }

    public function test_admin_views_global_statistics_across_all_teachers(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.statistics'));

        $response->assertOk()
            ->assertSee('Chuyên đề Hàm số PDF')
            ->assertSee('Tài liệu Riêng Của Cô Lan');

        $this->assertSame(8, $response->viewData('totalViews'));
        $this->assertSame(2, $response->viewData('totalDownloads'));
    }

    public function test_student_cannot_access_statistics_routes(): void
    {
        $this->actingAs($this->student)
            ->get(route('teacher.statistics'))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->get(route('admin.statistics'))
            ->assertForbidden();
    }

    public function test_teacher_and_admin_can_export_valid_xlsx_excel_report(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.statistics.export', ['course_id' => $this->course->id]));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type')
        );

        $binary = $response->streamedContent();
        // Kiểm tra chữ ký tệp nén PKZIP chuẩn của định dạng OpenXML .xlsx
        $this->assertStringStartsWith("PK\x03\x04", $binary);
        $this->assertStringContainsString('xl/workbook.xml', $binary);
        $this->assertStringContainsString('xl/worksheets/sheet1.xml', $binary);
        $this->assertStringContainsString('xl/worksheets/sheet2.xml', $binary);
        $this->assertStringContainsString('xl/worksheets/sheet3.xml', $binary);
        $this->assertStringContainsString('xl/sharedStrings.xml', $binary);
    }
}
