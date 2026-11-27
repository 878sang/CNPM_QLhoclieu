<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumReorderTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $otherTeacher;

    private User $student;

    private Course $course;

    private Chapter $chapter1;

    private Chapter $chapter2;

    private Chapter $chapter3;

    private Lesson $lessonA;

    private Lesson $lessonB;

    private Lesson $lessonC;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'display_name' => 'Quản trị viên']);
        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Giảng viên']);
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Học viên']);

        $this->teacher = User::factory()->create(['role_id' => $teacherRole->id, 'status' => 'active']);
        $this->otherTeacher = User::factory()->create(['role_id' => $teacherRole->id, 'status' => 'active']);
        $this->student = User::factory()->create(['role_id' => $studentRole->id, 'status' => 'active']);

        $subject = Subject::create(['name' => 'Hóa học', 'code' => 'CHEM12']);

        $this->course = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Khóa Hóa Học Hữu Cơ',
            'status' => 'public',
        ]);

        $this->chapter1 = Chapter::create(['course_id' => $this->course->id, 'title' => 'Chương 1: Este - Lipit', 'position' => 1]);
        $this->chapter2 = Chapter::create(['course_id' => $this->course->id, 'title' => 'Chương 2: Cacbohidrat', 'position' => 2]);
        $this->chapter3 = Chapter::create(['course_id' => $this->course->id, 'title' => 'Chương 3: Amin - Amino axit', 'position' => 3]);

        $this->lessonA = Lesson::create(['chapter_id' => $this->chapter1->id, 'title' => 'Bài 1: Khái niệm Este', 'position' => 1, 'status' => 'published']);
        $this->lessonB = Lesson::create(['chapter_id' => $this->chapter1->id, 'title' => 'Bài 2: Tính chất hóa học Este', 'position' => 2, 'status' => 'published']);
        $this->lessonC = Lesson::create(['chapter_id' => $this->chapter1->id, 'title' => 'Bài 3: Lipit và Chất béo', 'position' => 3, 'status' => 'draft']);
    }

    public function test_teacher_can_move_chapter_up_and_down(): void
    {
        // Chuyển Chương 2 lên đầu
        $this->actingAs($this->teacher)
            ->patch(route('courses.chapters.move', [$this->course, $this->chapter2]), [
                'direction' => 'up',
            ])
            ->assertRedirect();

        $this->assertSame(1, (int) $this->chapter2->fresh()->position);
        $this->assertSame(2, (int) $this->chapter1->fresh()->position);
        $this->assertSame(3, (int) $this->chapter3->fresh()->position);

        // Chuyển Chương 2 xuống lại
        $this->actingAs($this->teacher)
            ->patch(route('courses.chapters.move', [$this->course, $this->chapter2]), [
                'direction' => 'down',
            ])
            ->assertRedirect();

        $this->assertSame(1, (int) $this->chapter1->fresh()->position);
        $this->assertSame(2, (int) $this->chapter2->fresh()->position);
    }

    public function test_teacher_can_drag_and_drop_reorder_chapters_via_json(): void
    {
        $response = $this->actingAs($this->teacher)
            ->postJson(route('courses.chapters.reorder', $this->course), [
                'chapter_ids' => [$this->chapter3->id, $this->chapter1->id, $this->chapter2->id],
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, (int) $this->chapter3->fresh()->position);
        $this->assertSame(2, (int) $this->chapter1->fresh()->position);
        $this->assertSame(3, (int) $this->chapter2->fresh()->position);
    }

    public function test_teacher_can_move_lesson_up_down_and_preserve_published_status(): void
    {
        $this->actingAs($this->teacher)
            ->patch(route('courses.chapters.lessons.move', [$this->course, $this->chapter1, $this->lessonB]), [
                'direction' => 'up',
            ])
            ->assertRedirect();

        $this->assertSame(1, (int) $this->lessonB->fresh()->position);
        $this->assertSame('published', $this->lessonB->fresh()->status);
        $this->assertSame(2, (int) $this->lessonA->fresh()->position);
    }

    public function test_teacher_can_move_lesson_across_chapters_via_button_and_drag_drop(): void
    {
        // 1. Chuyển Bài 3 từ Chương 1 sang Chương 2 bằng form chọn chương
        $this->actingAs($this->teacher)
            ->patch(route('courses.chapters.lessons.move', [$this->course, $this->chapter1, $this->lessonC]), [
                'target_chapter_id' => $this->chapter2->id,
            ])
            ->assertRedirect();

        $this->assertSame($this->chapter2->id, (int) $this->lessonC->fresh()->chapter_id);
        $this->assertSame(1, (int) $this->lessonC->fresh()->position);

        // 2. Kéo thả Bài 1 sang đầu Chương 2 (trước Bài 3) bằng JSON payload
        $this->actingAs($this->teacher)
            ->postJson(route('courses.lessons.reorder', $this->course), [
                'chapters' => [
                    [
                        'chapter_id' => $this->chapter1->id,
                        'lesson_ids' => [$this->lessonB->id],
                    ],
                    [
                        'chapter_id' => $this->chapter2->id,
                        'lesson_ids' => [$this->lessonA->id, $this->lessonC->id],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame($this->chapter1->id, (int) $this->lessonB->fresh()->chapter_id);
        $this->assertSame(1, (int) $this->lessonB->fresh()->position);

        $this->assertSame($this->chapter2->id, (int) $this->lessonA->fresh()->chapter_id);
        $this->assertSame(1, (int) $this->lessonA->fresh()->position);
        $this->assertSame('published', $this->lessonA->fresh()->status);

        $this->assertSame($this->chapter2->id, (int) $this->lessonC->fresh()->chapter_id);
        $this->assertSame(2, (int) $this->lessonC->fresh()->position);
    }

    public function test_unauthorized_users_cannot_reorder_curriculum(): void
    {
        $this->actingAs($this->otherTeacher)
            ->postJson(route('courses.chapters.reorder', $this->course), [
                'chapter_ids' => [$this->chapter2->id, $this->chapter1->id],
            ])
            ->assertForbidden();

        $this->actingAs($this->student)
            ->postJson(route('courses.lessons.reorder', $this->course), [
                'chapter_id' => $this->chapter1->id,
                'lesson_ids' => [$this->lessonB->id, $this->lessonA->id],
            ])
            ->assertForbidden();
    }
}
