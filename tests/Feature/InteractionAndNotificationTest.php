<?php

namespace Tests\Feature;

use App\Mail\AppNotificationMail;
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
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class InteractionAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_edit_comment_without_changing_ownership_or_pin(): void
    {
        $comment = $this->comment();
        $this->actingAs($this->student)->patch(route('interactions.update', $comment), [
            'content' => '  Nội dung đã sửa  ', 'user_id' => $this->teacher->id, 'is_pinned' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('lesson_interactions', ['id' => $comment->id, 'content' => 'Nội dung đã sửa', 'user_id' => $this->student->id, 'is_pinned' => false]);
        $this->actingAs($this->student)->get(route('student.courses.learn', [$this->course, $this->lesson]))->assertOk()->assertSee('Nội dung đã sửa')->assertSee('Sửa bình luận');
    }

    public function test_author_can_edit_and_delete_their_reply(): void
    {
        $root = $this->comment();
        $reply = $this->comment(['parent_id' => $root->id]);
        $this->actingAs($this->student)->patch(route('interactions.update', $reply), ['content' => 'Phản hồi đã sửa'])->assertRedirect();
        $this->assertDatabaseHas('lesson_interactions', ['id' => $reply->id, 'content' => 'Phản hồi đã sửa']);
        $this->delete(route('interactions.destroy', $reply))->assertRedirect();
        $this->assertModelMissing($reply);
        $this->assertModelExists($root);
    }

    public function test_other_users_and_teacher_cannot_edit_student_content(): void
    {
        $comment = $this->comment();
        foreach ([$this->otherStudent, $this->teacher, $this->admin] as $user) {
            $this->actingAs($user)->patch(route('interactions.update', $comment), ['content' => 'Unauthorized edit'])->assertForbidden();
        }
        $this->assertDatabaseHas('lesson_interactions', ['id' => $comment->id, 'content' => 'Bình luận Sprint 4']);
    }

    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith([null])]
    public function test_empty_edit_is_rejected(?string $content): void
    {
        $comment = $this->comment();
        $this->actingAs($this->student)->patch(route('interactions.update', $comment), ['content' => $content])->assertSessionHasErrors(['content' => 'Vui lòng nhập nội dung bình luận.']);
        $this->assertDatabaseHas('lesson_interactions', ['id' => $comment->id, 'content' => 'Bình luận Sprint 4']);
    }

    public function test_edit_length_limit_and_html_escaping(): void
    {
        $comment = $this->comment();
        $this->actingAs($this->student)->patch(route('interactions.update', $comment), ['content' => str_repeat('a', 2001)])->assertSessionHasErrors('content');
        $html = '<script>alert(1)</script>';
        $this->patch(route('interactions.update', $comment), ['content' => $html])->assertSessionHasNoErrors();
        $this->get(route('student.courses.learn', [$this->course, $this->lesson]))->assertOk()->assertSee($html)->assertDontSee($html, false);
    }

    public function test_guest_cannot_edit_comment(): void
    {
        $comment = $this->comment();
        $this->patch(route('interactions.update', $comment), ['content' => 'Guest edit'])->assertRedirect(route('login'));
        $this->assertDatabaseHas('lesson_interactions', ['id' => $comment->id, 'content' => 'Bình luận Sprint 4']);
    }

    public function test_student_cannot_pin_and_other_teacher_cannot_moderate(): void
    {
        $comment = $this->comment();
        $this->actingAs($this->student)->patch(route('interactions.pin', $comment))->assertForbidden();
        $teacher = User::factory()->create(['role_id' => $this->teacher->role_id]);
        $this->actingAs($teacher)->patch(route('interactions.pin', $comment))->assertForbidden();
        $this->delete(route('interactions.destroy', $comment))->assertForbidden();
        $this->actingAs($this->teacher)->patch(route('interactions.pin', $comment))->assertRedirect();
        $this->assertTrue($comment->refresh()->is_pinned);
        $this->actingAs($this->admin)->patch(route('interactions.pin', $comment))->assertRedirect();
        $this->assertFalse($comment->refresh()->is_pinned);
    }

    public function test_deleting_root_removes_replies_and_likes(): void
    {
        $root = $this->comment();
        $reply = $this->comment(['parent_id' => $root->id]);
        $root->likedByUsers()->attach($this->student);
        $this->actingAs($this->teacher)->delete(route('interactions.destroy', $root))->assertRedirect();
        $this->assertModelMissing($root);
        $this->assertModelMissing($reply);
        $this->assertDatabaseCount('lesson_interaction_likes', 0);
    }

    public function test_hidden_or_nested_parent_and_unpublished_lesson_are_rejected(): void
    {
        $root = $this->comment(['status' => 'hidden']);
        $this->actingAs($this->student)->post(route('interactions.store', [$this->course, $this->lesson]), ['content' => 'Hidden reply', 'parent_id' => $root->id])->assertNotFound();
        $root->update(['status' => 'open']);
        $reply = $this->comment(['parent_id' => $root->id]);
        $this->post(route('interactions.store', [$this->course, $this->lesson]), ['content' => 'Nested reply', 'parent_id' => $reply->id])->assertNotFound();
        $this->lesson->update(['status' => 'draft']);
        $this->post(route('interactions.store', [$this->course, $this->lesson]), ['content' => 'Draft comment'])->assertNotFound();
        $this->assertDatabaseCount('lesson_interactions', 2);
    }

    public function test_unenrolled_author_cannot_edit_and_hidden_replies_are_not_rendered(): void
    {
        $root = $this->comment();
        $this->comment(['parent_id' => $root->id, 'status' => 'hidden', 'content' => 'Hidden reply secret']);
        $this->actingAs($this->student)->get(route('student.courses.learn', [$this->course, $this->lesson]))->assertOk()->assertDontSee('Hidden reply secret');
        $this->course->enrollments()->delete();
        $this->patch(route('interactions.update', $root), ['content' => 'Former student'])->assertForbidden();
    }

    public function test_notification_is_private_and_mark_all_only_affects_owner(): void
    {
        AppNotification::notifyUsers([$this->student, $this->otherStudent], 'Thông báo riêng', 'Nội dung');
        $other = AppNotification::where('user_id', $this->otherStudent->id)->firstOrFail();
        $this->actingAs($this->student)->get(route('notifications.read', $other))->assertForbidden();
        $this->delete(route('notifications.destroy', $other))->assertForbidden();
        $this->post(route('notifications.mark_all_read'))->assertRedirect();
        $this->assertNull($other->refresh()->read_at);
        $this->assertSame(0, AppNotification::where('user_id', $this->student->id)->unread()->count());
    }

    public function test_notification_email_is_queued_once_per_active_recipient(): void
    {
        Mail::fake();
        config(['mail.notification_emails' => true]);
        $this->otherStudent->update(['status' => 'locked']);
        AppNotification::notifyUsers([$this->student, $this->student, $this->otherStudent], 'Sprint 4', 'Có phản hồi mới', actionUrl: route('notifications.index'));
        Mail::assertQueued(AppNotificationMail::class, fn ($mail) => $mail->hasTo($this->student->email) && $mail->notificationTitle === 'Sprint 4' && $mail->notificationMessage === 'Có phản hồi mới');
        Mail::assertQueuedCount(1);
        $this->assertDatabaseCount('app_notifications', 2);
    }

    public function test_email_can_be_disabled_and_escapes_user_content(): void
    {
        Mail::fake();
        config(['mail.notification_emails' => false]);
        AppNotification::notifyUsers([$this->student], 'Title', 'Message');
        Mail::assertNothingQueued();
        $html = (new AppNotificationMail('<script>Title</script>', '<script>Message</script>', 'javascript:alert(1)'))->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    private function comment(array $attributes = []): LessonInteraction
    {
        return LessonInteraction::create(array_merge([
            'course_id' => $this->course->id, 'lesson_id' => $this->lesson->id,
            'user_id' => $this->student->id, 'type' => 'discussion', 'content' => 'Bình luận Sprint 4',
        ], $attributes));
    }

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
                'selected_answer' => 'true',
                'attempts' => 1,
                'correct_answer' => 'false',
                'stats' => [
                    'total' => 1,
                    'answered' => 1,
                    'correct' => 0,
                    'score_10' => 0,
                    'accuracy_percent' => 0,
                ],
            ]);

        // Học viên KHÔNG được phép sửa lại đáp án sau khi đã làm rồi (bị chặn 422 và giữ nguyên đáp án cũ)
        $this->actingAs($this->student)
            ->postJson(route('student.video-interactions.answer', $interaction), ['answer' => 'false'])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'already_answered' => true,
                'selected_answer' => 'true',
                'is_correct' => false,
                'attempts' => 1,
                'correct_answer' => 'false',
            ]);

        $this->assertDatabaseHas('video_interaction_responses', [
            'video_interaction_id' => $interaction->id,
            'user_id' => $this->student->id,
            'selected_answer' => 'true',
            'is_correct' => false,
            'attempts' => 1,
        ]);

        // Thêm câu hỏi thứ 2 và học viên trả lời đúng ngay lần đầu -> đạt 5.0/10 điểm
        $interaction2 = VideoInteraction::create([
            'course_id' => $this->course->id,
            'lesson_id' => $this->lesson->id,
            'document_id' => $this->videoDoc->id,
            'timestamp_seconds' => 60,
            'question_type' => 'true_false',
            'question' => 'Đạo hàm của hằng số bằng 0?',
            'options' => ['true' => 'Đúng', 'false' => 'Sai'],
            'correct_answer' => 'true',
            'explanation' => 'Đạo hàm của hằng số luôn bằng 0.',
            'is_required' => true,
            'created_by' => $this->teacher->id,
        ]);

        $this->actingAs($this->student)
            ->postJson(route('student.video-interactions.answer', $interaction2), ['answer' => 'true'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'is_correct' => true,
                'attempts' => 1,
                'stats' => [
                    'total' => 2,
                    'answered' => 2,
                    'correct' => 1,
                    'score_10' => 5,
                    'accuracy_percent' => 50,
                ],
            ]);

        // Trang học hiển thị câu hỏi tương tác, chế độ chỉ xem lại (không có nút Thử lại) & điểm tương tác tách riêng
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$this->course, $this->lesson]))
            ->assertOk()
            ->assertSee('Bài học có 2 lần tương tác (2 câu hỏi trên video)')
            ->assertSee('Điểm tương tác: 5.0/10')
            ->assertSee('Chế độ xem lại')
            ->assertDontSee('viRetryBtn');

        // Trang tiến độ học viên & tab Tiến độ của giảng viên cùng hiển thị Điểm tương tác video tách riêng
        $this->actingAs($this->student)
            ->get(route('student.progress'))
            ->assertOk()
            ->assertSee('5.0/10 điểm')
            ->assertSee('Đúng 1/2 câu');

        $this->actingAs($this->teacher)
            ->get(route('courses.show', ['course' => $this->course, 'tab' => 'progress']))
            ->assertOk()
            ->assertSee('Điểm tương tác video')
            ->assertSee('5.0/10 điểm');
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
