<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Document;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\SavedItem;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint5UatCompletionTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $teacherRole;

    private Role $studentRole;

    private User $admin;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'admin', 'display_name' => 'Quản trị viên']);
        $this->teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Giảng viên']);
        $this->studentRole = Role::create(['name' => 'student', 'display_name' => 'Học viên']);

        $this->admin = User::factory()->create(['role_id' => $this->adminRole->id, 'status' => 'active']);
        $this->teacher = User::factory()->create(['role_id' => $this->teacherRole->id, 'status' => 'active']);
        $this->student = User::factory()->create([
            'role_id' => $this->studentRole->id,
            'username' => 'hocvien01',
            'email' => 'hocvien01@example.com',
            'password' => 'password123',
            'status' => 'active',
        ]);
    }

    public function test_student_can_self_enroll_in_public_course_and_blocked_from_draft_course(): void
    {
        $subject = Subject::create(['name' => 'Tiếng Anh', 'code' => 'ENG12']);

        $publicCourse = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Luyện đề Tiếng Anh THPT Quốc Gia',
            'status' => 'public',
        ]);

        $draftCourse = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Khóa Tiếng Anh Đang Soạn',
            'status' => 'draft',
        ]);

        $this->actingAs($this->student)
            ->post(route('student.courses.enroll', $publicCourse))
            ->assertRedirect(route('student.courses.show', $publicCourse));

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->student->id,
            'course_id' => $publicCourse->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->student)
            ->post(route('student.courses.enroll', $draftCourse))
            ->assertForbidden();
    }

    public function test_student_can_bookmark_lessons_and_documents_and_manage_saved_library(): void
    {
        $subject = Subject::create(['name' => 'Sinh học', 'code' => 'BIO12']);
        $course = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Di truyền học Sinh 12',
            'status' => 'public',
        ]);
        $course->enrollments()->create(['user_id' => $this->student->id, 'status' => 'active']);

        $chapter = Chapter::create(['course_id' => $course->id, 'title' => 'Chương 1: Cơ chế di truyền', 'position' => 1]);
        $lesson = Lesson::create(['chapter_id' => $chapter->id, 'title' => 'Bài 1: Gen và Mã di truyền', 'position' => 1, 'status' => 'published']);
        $doc = Document::create([
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'type' => 'file',
            'title' => 'Sơ đồ tư duy Gen PDF',
            'file_path' => 'documents/gen.pdf',
            'original_name' => 'gen.pdf',
            'mime_type' => 'application/pdf',
            'allow_view' => true,
            'allow_download' => true,
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);

        // Lưu bài học
        $this->actingAs($this->student)
            ->post(route('student.saved.toggle'), ['lesson_id' => $lesson->id])
            ->assertRedirect();

        // Lưu tài liệu qua JSON
        $this->actingAs($this->student)
            ->postJson(route('student.saved.toggle'), ['document_id' => $doc->id])
            ->assertOk()
            ->assertJson(['success' => true, 'saved' => true]);

        // Xem trang Đã lưu
        $this->actingAs($this->student)
            ->get(route('student.saved'))
            ->assertOk()
            ->assertSee('Bài 1: Gen và Mã di truyền')
            ->assertSee('Sơ đồ tư duy Gen PDF');

        // Lọc chỉ tài liệu
        $this->actingAs($this->student)
            ->get(route('student.saved', ['type' => 'document']))
            ->assertOk()
            ->assertSee('Sơ đồ tư duy Gen PDF')
            ->assertDontSee('Bài giảng đã lưu');

        // Xóa khỏi danh sách Đã lưu
        $savedLessonItem = SavedItem::where('user_id', $this->student->id)->whereNull('document_id')->firstOrFail();
        $this->actingAs($this->student)
            ->delete(route('student.saved.destroy', $savedLessonItem))
            ->assertRedirect();

        $this->assertDatabaseMissing('saved_items', ['id' => $savedLessonItem->id]);
    }

    public function test_admin_can_lock_unlock_user_and_change_role_but_cannot_self_lock(): void
    {
        // Admin không thể tự khóa chính mình
        $this->actingAs($this->admin)
            ->patch(route('admin.users.toggle-status', $this->admin))
            ->assertRedirect()
            ->assertSessionHasErrors('status');
        $this->assertSame('active', $this->admin->fresh()->status);

        // Admin khóa tài khoản học viên
        $this->actingAs($this->admin)
            ->patch(route('admin.users.toggle-status', $this->student))
            ->assertRedirect();
        $this->assertSame('locked', $this->student->fresh()->status);

        // Học viên bị khóa không thể đăng nhập
        auth()->logout();
        $this->post(route('login.store'), [
            'login' => 'hocvien01',
            'password' => 'password123',
        ])->assertSessionHasErrors('login');
        $this->assertGuest();

        // Admin mở khóa lại và nâng quyền lên Giảng viên
        $this->actingAs($this->admin)
            ->patch(route('admin.users.toggle-status', $this->student))
            ->assertRedirect();
        $this->assertSame('active', $this->student->fresh()->status);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.update-role', $this->student), [
                'role_id' => $this->teacherRole->id,
            ])
            ->assertRedirect();
        $this->assertSame($this->teacherRole->id, (int) $this->student->fresh()->role_id);
    }
}
