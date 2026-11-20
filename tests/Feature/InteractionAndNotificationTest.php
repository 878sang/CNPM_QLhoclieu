<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonInteraction;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use App\Models\VideoInteraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteractionAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    private User $otherStudent;

    private Course $course;

    private Lesson $lesson;

    private Document $videoDoc;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'admin', 'display_name' => 'Quản trị viên']);
        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Giảng viên']);
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Học viên']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->teacher = User::factory()->create(['role_id' => $teacherRole->id]);
        $this->student = User::factory()->create(['role_id' => $studentRole->id]);
        $this->otherStudent = User::factory()->create(['role_id' => $studentRole->id]);

        $subject = Subject::create(['name' => 'Toán học', 'code' => 'MATH101']);
        $this->course = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Giải tích cơ bản',
            'status' => 'public',
        ]);

        Enrollment::create([
            'course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'status' => 'active',
        ]);

        $chapter = Chapter::create([
            'course_id' => $this->course->id,
            'title' => 'Chương 1',
            'position' => 1,
        ]);

        $this->lesson = Lesson::create([
            'chapter_id' => $chapter->id,
            'title' => 'Bài 1: Đạo hàm',
            'position' => 1,
            'status' => 'published',
        ]);

        $this->videoDoc = Document::create([
            'course_id' => $this->course->id,
            'lesson_id' => $this->lesson->id,
            'type' => 'video',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'title' => 'Video bài giảng Đạo hàm',
            'allow_view' => true,
            'allow_download' => false,
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);
    }

    public function test_teacher_can_manage_interactive_video_questions(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('courses.video-interactions.manage', [$this->course, $this->videoDoc]))
            ->assertOk()
            ->assertSee('Video bài giảng Đạo hàm');

        // Tạo câu trắc nghiệm
        $this->actingAs($this->teacher)
            ->post(route('courses.video-interactions.store', [$this->course, $this->videoDoc]), [
                'timestamp_input' => '01:15',
                'question_type' => 'multiple_choice',
                'question' => 'Đạo hàm của x^2 là gì?',
                'option_a' => '2x',
                'option_b' => 'x',
                'option_c' => 'x^2',
                'option_d' => '2',
                'correct_answer' => 'A',
                'explanation' => 'Áp dụng công thức (x^n)\' = n*x^(n-1).',
                'is_required' => '1',
            ])
            ->assertRedirect(route('courses.video-interactions.manage', [$this->course, $this->videoDoc]));

        // Tạo câu Đúng/Sai
        $this->actingAs($this->teacher)
            ->post(route('courses.video-interactions.store', [$this->course, $this->videoDoc]), [
                'timestamp_input' => '02:00',
                'question_type' => 'true_false',
                'question' => 'Đạo hàm của hằng số bằng 0.',
                'correct_answer' => 'true',
                'is_required' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('video_interactions', [
            'document_id' => $this->videoDoc->id,
            'timestamp_seconds' => 75,
            'question_type' => 'multiple_choice',
            'correct_answer' => 'A',
        ]);

        $this->assertDatabaseHas('video_interactions', [
            'document_id' => $this->videoDoc->id,
            'timestamp_seconds' => 120,
            'question_type' => 'true_false',
            'correct_answer' => 'true',
        ]);

        $interaction = VideoInteraction::first();
        $this->actingAs($this->teacher)
            ->delete(route('courses.video-interactions.destroy', [$this->course, $this->videoDoc, $interaction]))
            ->assertRedirect();

        $this->assertDatabaseMissing('video_interactions', ['id' => $interaction->id]);
    }

    public function test_student_can_answer_video_interaction_and_unenrolled_cannot(): void
    {
        $interaction = VideoInteraction::create([
            'course_id' => $this->course->id,
            'lesson_id' => $this->lesson->id,
            'document_id' => $this->videoDoc->id,
            'timestamp_seconds' => 30,
            'question_type' => 'true_false',
            'question' => 'Hàm số liên tục thì luôn có đạo hàm?',
            'options' => ['true' => 'Đúng', 'false' => 'Sai'],
            'correct_answer' => 'false',
            'explanation' => 'Ví dụ y = |x| liên tục tại 0 nhưng không có đạo hàm tại 0.',
            'is_required' => true,
            'created_by' => $this->teacher->id,
        ]);

        // Học viên chưa ghi danh bị chặn 403
        $this->actingAs($this->otherStudent)
            ->postJson(route('student.video-interactions.answer', $interaction), ['answer' => 'false'])
            ->assertForbidden();

        // Học viên đã ghi danh trả lời sai lần 1
        $this->actingAs($this->student)
            ->postJson(route('student.video-interactions.answer', $interaction), ['answer' => 'true'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'is_correct' => false,
                'attempts' => 1,
                'correct_answer' => 'false',
            ]);

        // Thử lại và trả lời đúng lần 2
        $this->actingAs($this->student)
            ->postJson(route('student.video-interactions.answer', $interaction), ['answer' => 'false'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'is_correct' => true,
                'attempts' => 2,
                'stats' => [
                    'total' => 1,
                    'answered' => 1,
                    'correct' => 1,
                    'score_10' => 10,
                    'accuracy_percent' => 100,
                ],
            ]);

        // Trang học hiển thị câu hỏi tương tác & điểm tương tác tách riêng
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$this->course, $this->lesson]))
            ->assertOk()
            ->assertSee('Bài học có 1 lần tương tác (1 câu hỏi trên video)')
            ->assertSee('Điểm tương tác: 10.0/10');

        // Trang tiến độ học viên & tab Tiến độ của giảng viên cùng hiển thị Điểm tương tác video tách riêng
        $this->actingAs($this->student)
            ->get(route('student.progress'))
            ->assertOk()
            ->assertSee('10.0/10 điểm')
            ->assertSee('Đúng 1/1 câu');

        $this->actingAs($this->teacher)
            ->get(route('courses.show', ['course' => $this->course, 'tab' => 'progress']))
            ->assertOk()
            ->assertSee('Điểm tương tác video')
            ->assertSee('10.0/10 điểm');
    }

    public function test_teacher_can_batch_create_and_edit_video_interactions(): void
    {
        // Tạo liên tiếp 3 câu hỏi (2 câu cùng mốc 00:45 và 1 câu ở mốc 01:30) trong 1 lần gửi
        $this->actingAs($this->teacher)
            ->post(route('courses.video-interactions.store', [$this->course, $this->videoDoc]), [
                'items' => [
                    [
                        'timestamp_input' => '00:45',
                        'question_type' => 'multiple_choice',
                        'question' => 'Câu 1: 2 + 2 bằng mấy?',
                        'option_a' => '3',
                        'option_b' => '4',
                        'option_c' => '5',
                        'correct_answer' => 'B',
                        'explanation' => '2 + 2 = 4',
                        'is_required' => '1',
                    ],
                    [
                        'timestamp_input' => '00:45',
                        'question_type' => 'multiple_choice',
                        'question' => 'Câu 2: 3 * 3 bằng mấy?',
                        'option_a' => '6',
                        'option_b' => '9',
                        'correct_answer' => 'B',
                        'is_required' => '1',
                    ],
                    [
                        'timestamp_input' => '01:30',
                        'question_type' => 'true_false',
                        'question' => 'Câu 3: 10 > 5 là đúng.',
                        'correct_answer' => 'true',
                        'is_required' => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('courses.video-interactions.manage', [$this->course, $this->videoDoc]));

        $this->assertEquals(3, VideoInteraction::where('document_id', $this->videoDoc->id)->count());

        // Sửa câu hỏi đầu tiên sau khi thêm
        $first = VideoInteraction::where('document_id', $this->videoDoc->id)->orderBy('id')->firstOrFail();
        $this->actingAs($this->teacher)
            ->put(route('courses.video-interactions.update', [$this->course, $this->videoDoc, $first]), [
                'timestamp_input' => '00:50',
                'question_type' => 'multiple_choice',
                'question' => 'Câu 1 (đã sửa): 2 + 3 bằng mấy?',
                'option_a' => '5',
                'option_b' => '6',
                'correct_answer' => 'A',
                'explanation' => '2 + 3 = 5',
                'is_required' => '1',
            ])
            ->assertRedirect(route('courses.video-interactions.manage', [$this->course, $this->videoDoc]));

        $this->assertDatabaseHas('video_interactions', [
            'id' => $first->id,
            'timestamp_seconds' => 50,
            'question' => 'Câu 1 (đã sửa): 2 + 3 bằng mấy?',
            'correct_answer' => 'A',
        ]);

        // Trang Quản lý tương tác video tách riêng hiển thị video & câu hỏi
        $this->actingAs($this->teacher)
            ->get(route('video-interactions.index'))
            ->assertOk()
            ->assertSee('Quản lý tương tác video')
            ->assertSee('Video bài giảng Đạo hàm')
            ->assertSee('Câu 1 (đã sửa): 2 + 3 bằng mấy?');
    }

    public function test_lesson_interaction_workflow_and_auto_notifications(): void
    {
        // Học viên đặt câu hỏi trong bài học (gắn mốc mm:ss)
        $this->actingAs($this->student)
            ->post(route('interactions.store', [$this->course, $this->lesson]), [
                'type' => 'question',
                'content' => 'Thầy ơi đoạn phút 01:10 em chưa rõ cách biến đổi ạ.',
                'video_timestamp_input' => '01:10',
            ])
            ->assertRedirect();

        $interaction = LessonInteraction::firstOrFail();
        $this->assertEquals(70, $interaction->video_timestamp_seconds);

        // Giảng viên tự động nhận được thông báo
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->teacher->id,
            'type' => AppNotification::TYPE_INTERACTION,
            'course_id' => $this->course->id,
        ]);

        // Giảng viên vào trang Quản lý hỏi đáp: có trình phát video đối chiếu & nút xem đúng đoạn 01:10
        $this->actingAs($this->teacher)
            ->get(route('interactions.index', ['has_timestamp' => 1]))
            ->assertOk()
            ->assertSee('Thầy ơi đoạn phút 01:10')
            ->assertSee('VIDEO ĐỐI CHIẾU THẮC MẮC')
            ->assertSee('Xem đoạn 01:10');

        $this->actingAs($this->teacher)
            ->post(route('interactions.reply', $interaction), [
                'content' => 'Em xem lại công thức đạo hàm hợp nhé!',
                'mark_resolved' => '1',
            ])
            ->assertRedirect();

        $interaction->refresh();
        $this->assertEquals(LessonInteraction::STATUS_RESOLVED, $interaction->status);

        // Học viên tự động nhận được thông báo phản hồi
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->student->id,
            'type' => AppNotification::TYPE_INTERACTION,
        ]);

        // Thích tương tác
        $this->actingAs($this->student)
            ->postJson(route('interactions.like', $interaction))
            ->assertOk()
            ->assertJson(['liked' => true, 'likes_count' => 1]);
    }

    public function test_notification_management_send_and_read(): void
    {
        // Giảng viên gửi thông báo cho học viên trong khóa học
        $this->actingAs($this->teacher)
            ->post(route('notifications.store'), [
                'target_type' => 'course',
                'course_id' => $this->course->id,
                'type' => AppNotification::TYPE_COURSE,
                'title' => 'Lịch ôn tập cuối tuần',
                'message' => 'Các em nhớ xem hết video chương 1 trước Chủ nhật.',
            ])
            ->assertRedirect(route('notifications.index', ['tab' => 'sent']));

        $notification = AppNotification::where('user_id', $this->student->id)->firstOrFail();
        $this->assertFalse($notification->isRead());

        // Học viên xem hộp thư thông báo
        $this->actingAs($this->student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Lịch ôn tập cuối tuần');

        // Đánh dấu đã đọc
        $this->actingAs($this->student)
            ->get(route('notifications.read', $notification))
            ->assertRedirect();

        $notification->refresh();
        $this->assertTrue($notification->isRead());

        // Admin gửi thông báo toàn hệ thống
        $this->actingAs($this->admin)
            ->post(route('notifications.store'), [
                'target_type' => 'all',
                'type' => AppNotification::TYPE_SYSTEM,
                'title' => 'Bảo trì hệ thống',
                'message' => 'Hệ thống bảo trì lúc 23:00.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->student->id,
            'title' => 'Bảo trì hệ thống',
        ]);

        // Đánh dấu tất cả đã đọc
        $this->actingAs($this->student)
            ->post(route('notifications.mark_all_read'))
            ->assertRedirect();

        $this->assertEquals(0, AppNotification::where('user_id', $this->student->id)->unread()->count());
    }

    public function test_students_receive_auto_notifications_for_new_lessons_and_assignments(): void
    {
        // 1. Bài giảng mới được Admin duyệt xuất bản -> học viên đang ghi danh nhận thông báo
        $pendingLesson = Lesson::create([
            'chapter_id' => $this->lesson->chapter_id,
            'title' => 'Bài 2: Tích phân',
            'position' => 2,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.lessons.approve', $pendingLesson))
            ->assertRedirect();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'type' => AppNotification::TYPE_COURSE,
            'title' => "Bài giảng mới trong khóa \"{$this->course->title}\"",
        ]);
        // Học viên chưa ghi danh không nhận thông báo
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $this->otherStudent->id,
            'course_id' => $this->course->id,
            'type' => AppNotification::TYPE_COURSE,
        ]);

        // 2. Bài tập mới được mở (Admin tạo published) -> học viên đang ghi danh nhận thông báo
        $this->actingAs($this->admin)
            ->post(route('courses.assignments.store', $this->course), [
                'title' => 'Bài tập về nhà Chương 1',
                'type' => 'text',
                'question_count' => 3,
                'status' => 'published',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'type' => AppNotification::TYPE_ASSIGNMENT,
            'title' => "Bài tập mới trong khóa \"{$this->course->title}\"",
        ]);
    }
}
