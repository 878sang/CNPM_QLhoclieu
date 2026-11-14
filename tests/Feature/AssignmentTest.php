<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $teacherRole = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Giáo viên']);
        $studentRole = Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Học viên']);

        $this->teacher = User::factory()->create(['role_id' => $teacherRole->id]);
        $this->student = User::factory()->create(['role_id' => $studentRole->id]);

        $subject = Subject::create(['name' => 'Toán', 'code' => 'TOAN']);
        $this->course = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Toán 12 Cơ bản',
            'status' => 'public',
        ]);

        Enrollment::create([
            'course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'status' => 'active',
        ]);
    }

    public function test_create_assignment_form_starts_with_zero_questions(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('courses.assignments.create', $this->course))
            ->assertOk()
            ->assertSee('value="0"', false)
            ->assertSee('Thêm tiếp...')
            ->assertDontSee('+1 Trắc nghiệm')
            ->assertDontSee('+5 TN');
    }

    public function test_teacher_can_create_text_assignment_with_answer_key(): void
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('courses.assignments.store', $this->course), [
                'title' => 'Bài tập giới hạn',
                'description' => 'Làm các câu hỏi tự luận số',
                'type' => 'text',
                'question_count' => 5,
                'status' => 'published',
                'answer_key' => [
                    '1' => '18432',
                    '2' => '303',
                    '3' => '3456',
                    '4' => '2520',
                    '5' => '455',
                ],
            ]);

        $response->assertRedirect(route('courses.assignments.index', $this->course));

        $this->assertDatabaseHas('assignments', [
            'course_id' => $this->course->id,
            'title' => 'Bài tập giới hạn',
            'type' => 'text',
            'question_count' => 5,
            'status' => 'published',
        ]);
    }

    public function test_teacher_can_create_multiple_choice_assignment(): void
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('courses.assignments.store', $this->course), [
                'title' => 'Trắc nghiệm Đơn điệu hàm số',
                'type' => 'multiple_choice',
                'question_count' => 4,
                'status' => 'published',
                'answer_key' => [
                    '1' => 'A',
                    '2' => 'B',
                    '3' => 'C',
                    '4' => 'D',
                ],
            ]);

        $response->assertRedirect(route('courses.assignments.index', $this->course));

        $assignment = Assignment::where('title', 'Trắc nghiệm Đơn điệu hàm số')->first();
        $this->assertNotNull($assignment);
        $this->assertSame('multiple_choice', $assignment->type);
        $this->assertSame(['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'], $assignment->answer_key);
    }

    public function test_teacher_can_update_assignment_and_append_questions(): void
    {
        $assignment = Assignment::create([
            'course_id' => $this->course->id,
            'title' => 'Bài tập gốc',
            'type' => 'multiple_choice',
            'question_count' => 2,
            'status' => 'draft',
            'answer_key' => ['1' => 'A', '2' => 'B'],
        ]);

        // Cập nhật tăng số câu lên 4 và giữ nguyên câu 1, 2, thêm câu 3, 4
        $response = $this->actingAs($this->teacher)
            ->put(route('courses.assignments.update', [$this->course, $assignment]), [
                'title' => 'Bài tập cập nhật',
                'type' => 'multiple_choice',
                'question_count' => 4,
                'status' => 'published',
                'answer_key' => [
                    '1' => 'A',
                    '2' => 'B',
                    '3' => 'C',
                    '4' => 'D',
                ],
            ]);

        $response->assertRedirect(route('courses.assignments.show', [$this->course, $assignment]));

        $assignment->refresh();
        $this->assertSame('Bài tập cập nhật', $assignment->title);
        $this->assertSame(4, $assignment->question_count);
        $this->assertSame(['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'], $assignment->answer_key);
    }

    public function test_student_can_view_assignments_and_submit_answers(): void
    {
        $assignment = Assignment::create([
            'course_id' => $this->course->id,
            'title' => 'Đề thi trắc nghiệm',
            'type' => 'multiple_choice',
            'question_count' => 4,
            'status' => 'published',
            'show_answer_to_students' => true,
            'answer_key' => [
                '1' => 'A',
                '2' => 'B',
                '3' => 'C',
                '4' => 'D',
            ],
        ]);

        // Học viên vào trang danh sách bài tập
        $this->actingAs($this->student)
            ->get(route('student.courses.assignments', $this->course))
            ->assertOk()
            ->assertSee('Đề thi trắc nghiệm');

        // Học viên vào làm bài
        $this->actingAs($this->student)
            ->get(route('student.assignments.do', [$this->course, $assignment]))
            ->assertOk()
            ->assertSee('Câu 1')
            ->assertSee('Câu 4');

        // Học viên nộp bài: đúng câu 1, 2, 3; sai câu 4
        $submitResponse = $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->course, $assignment]), [
                'answers' => [
                    '1' => 'A',
                    '2' => 'B',
                    '3' => 'C',
                    '4' => 'A', // sai
                ],
            ]);

        $submitResponse->assertRedirect(route('student.assignments.result', [$this->course, $assignment]));

        // Kiểm tra chấm điểm tự động: 3/4 câu đúng = 3 điểm
        $submission = Submission::where('assignment_id', $assignment->id)->first();
        $this->assertNotNull($submission);
        $this->assertEquals(3.0, (float) $submission->score);
        $this->assertEquals(4.0, (float) $submission->max_score);

        // Học viên xem kết quả
        $this->actingAs($this->student)
            ->get(route('student.assignments.result', [$this->course, $assignment]))
            ->assertOk()
            ->assertSee('3')
            ->assertSee('Đúng')
            ->assertSee('Sai');
    }

    public function test_teacher_can_grade_submission_manually(): void
    {
        $assignment = Assignment::create([
            'course_id' => $this->course->id,
            'title' => 'Bài tự luận số',
            'type' => 'text',
            'question_count' => 2,
            'status' => 'published',
        ]);

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->student->id,
            'answers' => ['1' => 'x = 5', '2' => 'y = 10'],
            'submitted_at' => now(),
        ]);

        // Giáo viên vào xem danh sách nộp bài
        $this->actingAs($this->teacher)
            ->get(route('courses.assignments.submissions.index', [$this->course, $assignment]))
            ->assertOk()
            ->assertSee($this->student->name);

        // Giáo viên chấm điểm và nhận xét
        $response = $this->actingAs($this->teacher)
            ->post(route('courses.assignments.submissions.grade', [$this->course, $assignment, $submission]), [
                'score' => 2.0,
                'feedback' => 'Bài làm rất tốt, trình bày rõ ràng.',
            ]);

        $response->assertRedirect();

        $submission->refresh();
        $this->assertEquals(2.0, (float) $submission->score);
        $this->assertSame('Bài làm rất tốt, trình bày rõ ràng.', $submission->feedback);
        $this->assertSame($this->teacher->id, $submission->graded_by);
    }

    public function test_teacher_can_view_student_submission_details(): void
    {
        $assignment = Assignment::create([
            'course_id' => $this->course->id,
            'title' => 'Kiểm tra 1 tiết',
            'type' => 'mixed',
            'question_count' => 3,
            'question_types' => ['1' => 'multiple_choice', '2' => 'multiple_choice', '3' => 'text'],
            'answer_key' => ['1' => 'A', '2' => 'B', '3' => '100'],
            'status' => 'published',
        ]);

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->student->id,
            'answers' => ['1' => 'A', '2' => 'C', '3' => '100'],
            'score' => 2.0,
            'max_score' => 3.0,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('courses.assignments.submissions.show', [$this->course, $assignment, $submission]));

        $response->assertOk()
            ->assertSee($this->student->name)
            ->assertSee('Trắc nghiệm')
            ->assertSee('Điền số')
            ->assertSee('Đúng')
            ->assertSee('Sai')
            ->assertSee('100');
    }

    public function test_teacher_can_create_and_manage_mixed_assignment(): void
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('courses.assignments.store', $this->course), [
                'title' => 'Đề hỗn hợp Trắc nghiệm và Tự luận số',
                'type' => 'mixed',
                'question_count' => 4,
                'status' => 'published',
                'question_types' => [
                    '1' => 'multiple_choice',
                    '2' => 'multiple_choice',
                    '3' => 'text',
                    '4' => 'text',
                ],
                'answer_key' => [
                    '1' => 'A',
                    '2' => 'B',
                    '3' => '18432',
                    '4' => '303',
                ],
            ]);

        $response->assertRedirect(route('courses.assignments.index', $this->course));

        $assignment = Assignment::where('title', 'Đề hỗn hợp Trắc nghiệm và Tự luận số')->first();
        $this->assertNotNull($assignment);
        $this->assertSame('mixed', $assignment->type);
        $this->assertTrue($assignment->isMultipleChoiceQuestion(1));
        $this->assertFalse($assignment->isMultipleChoiceQuestion(3));

        // Học viên vào làm bài tập hỗn hợp
        $this->actingAs($this->student)
            ->get(route('student.assignments.do', [$this->course, $assignment]))
            ->assertOk()
            ->assertSee('Trắc nghiệm')
            ->assertSee('Điền số');

        // Học viên nộp bài: đúng câu 1, 2, 3; sai câu 4
        $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->course, $assignment]), [
                'answers' => [
                    '1' => 'A',
                    '2' => 'B',
                    '3' => '18432',
                    '4' => '999',
                ],
            ]);

        $submission = $assignment->submissions()->where('user_id', $this->student->id)->first();
        $this->assertNotNull($submission);
        $this->assertEquals(3.0, (float) $submission->score);

        // Giáo viên vào xem bài làm của học sinh
        $this->actingAs($this->teacher)
            ->get(route('courses.assignments.submissions.show', [$this->course, $assignment, $submission]))
            ->assertOk()
            ->assertSee('18432')
            ->assertSee('999');
    }
}
