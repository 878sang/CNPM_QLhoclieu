<?php

namespace Tests\Feature;

use App\Models\AiTutorMessage;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use App\Services\GeminiTutorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiTutorTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $otherStudent;

    private Course $course;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Giảng viên']);
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Học viên']);

        $teacher = User::factory()->create(['role_id' => $teacherRole->id]);
        $this->student = User::factory()->create(['role_id' => $studentRole->id]);
        $this->otherStudent = User::factory()->create(['role_id' => $studentRole->id]);

        $subject = Subject::create(['name' => 'Toán học', 'code' => 'MATH101']);
        $this->course = Course::create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Giải tích 12',
            'status' => 'public',
        ]);

        Enrollment::create([
            'course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'status' => 'active',
        ]);

        $chapter = Chapter::create([
            'course_id' => $this->course->id,
            'title' => 'Chương 1: Đạo hàm',
            'position' => 1,
        ]);

        $this->lesson = Lesson::create([
            'chapter_id' => $chapter->id,
            'title' => 'Bài 1: Khái niệm đạo hàm',
            'position' => 1,
            'status' => 'published',
        ]);

        Document::create([
            'course_id' => $this->course->id,
            'lesson_id' => $this->lesson->id,
            'type' => 'video',
            'url' => 'https://youtu.be/dQw4w9WgXcQ?si=test',
            'title' => 'Video bài giảng Đạo hàm',
            'allow_view' => true,
            'allow_download' => false,
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);
    }

    public function test_student_can_ask_ai_tutor_about_youtube_video_and_follow_up(): void
    {
        config()->set('services.gemini.api_key', 'test-api-key');
        config()->set('services.gemini.model', 'gemini-4-hydrogen');

        Http::fake([
            'https://generativelanguage.googleapis.com/v1/interactions' => Http::sequence()
                ->push([
                    'id' => 'int_turn_1',
                    'steps' => [
                        [
                            'type' => 'model_output',
                            'content' => [
                                ['type' => 'text', 'text' => 'Tại mốc 01:20, giảng viên giải thích công thức đạo hàm bậc nhất.'],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'id' => 'int_turn_2',
                    'steps' => [
                        [
                            'type' => 'model_output',
                            'content' => [
                                ['type' => 'text', 'text' => 'Ví dụ cụ thể là đạo hàm của x^2 bằng 2x.'],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        // Kiểm tra trang học hiển thị nút nổi & popup Trợ lý AI sạch gọn (không có tab riêng, không có thanh "Đang đọc:" hay cảnh báo GEMINI_API_KEY)
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$this->course, $this->lesson]))
            ->assertOk()
            ->assertSee('id="aiTutorFab"', false)
            ->assertSee('id="aiTutorPopup"', false)
            ->assertSee('id="aiTutorImageInput"', false)
            ->assertDontSee('id="st-tab-ai-head"', false)
            ->assertDontSee('GEMINI_API_KEY', false)
            ->assertDontSee('Đang đọc:', false);

        // Lượt 1: gửi câu hỏi kèm mốc 01:20 -> kiểm tra payload gửi YouTube video URL tới Interactions API v1
        $this->actingAs($this->student)
            ->postJson(route('student.ai-tutor.ask', [$this->course, $this->lesson]), [
                'question' => 'Đoạn này giảng gì vậy?',
                'video_timestamp_seconds' => 80,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'user_message' => [
                    'formatted_timestamp' => '01:20',
                ],
                'assistant_message' => [
                    'content' => 'Tại mốc 01:20, giảng viên giải thích công thức đạo hàm bậc nhất.',
                ],
            ]);

        // Lượt 2: hỏi tiếp -> tự dùng previous_interaction_id = int_turn_1
        $this->actingAs($this->student)
            ->postJson(route('student.ai-tutor.ask', [$this->course, $this->lesson]), [
                'question' => 'Cho mình 1 ví dụ cụ thể',
            ])
            ->assertOk()
            ->assertJsonPath('assistant_message.content', 'Ví dụ cụ thể là đạo hàm của x^2 bằng 2x.');

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            $data = $request->data();
            if (($data['previous_interaction_id'] ?? null) === 'int_turn_1') {
                return $data['model'] === 'gemini-4-hydrogen';
            }

            $step = $data['input'][0] ?? [];
            $content = $step['content'] ?? [];
            $hasYoutubeVideo = ($step['type'] ?? null) === 'user_input'
                && collect($content)->contains(fn ($part) => ($part['type'] ?? null) === 'video'
                    && ($part['uri'] ?? null) === 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

            return $data['model'] === 'gemini-4-hydrogen' && $hasYoutubeVideo;
        });

        $this->assertEquals(4, AiTutorMessage::where('user_id', $this->student->id)->count());

        // Làm mới hội thoại
        $this->actingAs($this->student)
            ->deleteJson(route('student.ai-tutor.reset', [$this->course, $this->lesson]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals(0, AiTutorMessage::where('user_id', $this->student->id)->count());
    }

    public function test_missing_api_key_and_unenrolled_student_are_handled(): void
    {
        // Học viên chưa ghi danh bị chặn 403
        $this->actingAs($this->otherStudent)
            ->postJson(route('student.ai-tutor.ask', [$this->course, $this->lesson]), [
                'question' => 'Xin chào AI',
            ])
            ->assertForbidden();

        // Thiếu API key trả lỗi 422 thân thiện
        config()->set('services.gemini.api_key', '');
        $this->actingAs($this->student)
            ->postJson(route('student.ai-tutor.ask', [$this->course, $this->lesson]), [
                'question' => 'Tóm tắt video giúp mình',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    public function test_student_can_send_image_to_ai_tutor_and_view_own_image(): void
    {
        Storage::fake();
        config()->set('services.gemini.api_key', 'test-api-key');
        config()->set('services.gemini.model', 'gemini-4-hydrogen');

        Http::fake([
            'https://generativelanguage.googleapis.com/v1/interactions' => Http::response([
                'id' => 'int_img_1',
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [
                            ['type' => 'text', 'text' => 'Trong ảnh là bài toán tính đạo hàm bậc nhất.'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Tạo ảnh PNG 1x1 hợp lệ không phụ thuộc GD extension
        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $imageFile = UploadedFile::fake()->createWithContent('bai-tap.png', $tinyPng);

        $response = $this->actingAs($this->student)
            ->post(route('student.ai-tutor.ask', [$this->course, $this->lesson]), [
                'question' => 'Giải bài trong ảnh giúp mình',
                'image' => $imageFile,
            ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assistant_message.content', 'Trong ảnh là bài toán tính đạo hàm bậc nhất.');

        Http::assertSent(function ($request) {
            $step = $request->data()['input'][0] ?? [];
            $content = $step['content'] ?? [];

            return ($step['type'] ?? null) === 'user_input'
                && collect($content)->contains(fn ($part) => ($part['type'] ?? null) === 'image'
                    && ($part['mime_type'] ?? null) === 'image/png'
                    && ! empty($part['data']));
        });

        $userMsg = AiTutorMessage::where('user_id', $this->student->id)
            ->where('role', AiTutorMessage::ROLE_USER)
            ->firstOrFail();

        $this->assertNotNull($userMsg->image_path);
        Storage::assertExists($userMsg->image_path);

        // Chủ sở hữu xem được ảnh, học viên khác bị chặn 403
        $this->actingAs($this->student)
            ->get(route('student.ai-tutor.image', $userMsg))
            ->assertOk();

        $this->actingAs($this->otherStudent)
            ->get(route('student.ai-tutor.image', $userMsg))
            ->assertForbidden();

        // Reset hội thoại sẽ xóa luôn tệp ảnh đính kèm
        $savedPath = $userMsg->image_path;
        $this->actingAs($this->student)
            ->deleteJson(route('student.ai-tutor.reset', [$this->course, $this->lesson]))
            ->assertOk();

        Storage::assertMissing($savedPath);
    }

    public function test_background_prepare_caches_lesson_context_and_uses_fast_model(): void
    {
        config()->set('services.gemini.api_key', 'test-api-key');
        config()->set('services.gemini.model', 'gemini-4-hydrogen');
        config()->set('services.gemini.fast_model', 'gemini-3.5-flash-lite');

        Http::fake([
            'https://generativelanguage.googleapis.com/v1/interactions' => Http::sequence()
                ->push([
                    'id' => 'int_prepare_1',
                    'steps' => [
                        [
                            'type' => 'model_output',
                            'content' => [
                                ['type' => 'text', 'text' => 'Mục lục video: 00:30 Khái niệm đạo hàm; 02:15 Công thức đạo hàm x^n.'],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'id' => 'int_fast_qa_1',
                    'steps' => [
                        [
                            'type' => 'model_output',
                            'content' => [
                                ['type' => 'text', 'text' => 'Tại mốc 02:15, giảng viên giới thiệu công thức đạo hàm x^n = n*x^(n-1).'],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        // 1. Đọc ngầm lần đầu -> dùng model sâu (gemini-4-hydrogen) kèm video và lưu cache vào lessons.ai_context_text
        $this->actingAs($this->student)
            ->postJson(route('student.ai-tutor.prepare', [$this->course, $this->lesson]))
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'ready', 'cached' => false]);

        $this->lesson->refresh();
        $this->assertNotNull($this->lesson->ai_context_text);
        $this->assertNotNull($this->lesson->ai_context_hash);

        // 2. Gọi prepare lần 2 -> trúng cache DB ngay lập tức, KHÔNG gọi thêm Gemini
        $this->actingAs($this->student)
            ->postJson(route('student.ai-tutor.prepare', [$this->course, $this->lesson]))
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'ready', 'cached' => true]);

        // 3. Học viên hỏi AI -> dùng thẳng bản ghi chú + model siêu nhanh (gemini-3.5-flash-lite, thinking minimal)
        $this->actingAs($this->student)
            ->postJson(route('student.ai-tutor.ask', [$this->course, $this->lesson]), [
                'question' => 'Đoạn 02:15 giảng gì?',
            ])
            ->assertOk()
            ->assertJsonPath('assistant_message.content', 'Tại mốc 02:15, giảng viên giới thiệu công thức đạo hàm x^n = n*x^(n-1).');

        Http::assertSentCount(2);

        $recorded = Http::recorded();
        $preparePayload = $recorded[0][0]->data();
        $qaPayload = $recorded[1][0]->data();
        $qaContent = $qaPayload['input'][0]['content'] ?? [];

        $this->assertSame('gemini-4-hydrogen', $preparePayload['model'] ?? null);
        $this->assertSame('gemini-3.5-flash-lite', $qaPayload['model'] ?? null);
        $this->assertSame('minimal', $qaPayload['generation_config']['thinking_level'] ?? null);
        $this->assertFalse(collect($qaContent)->contains(fn ($p) => ($p['type'] ?? null) === 'video'));
        $this->assertTrue(collect($qaContent)->contains(fn ($p) => ($p['type'] ?? null) === 'text'
            && str_contains($p['text'] ?? '', '02:15 Công thức đạo hàm')));
    }

    public function test_streaming_sse_and_quick_prompt_cache_work_end_to_end(): void
    {
        config()->set('services.gemini.api_key', 'test-api-key');
        config()->set('services.gemini.model', 'gemini-4-hydrogen');
        config()->set('services.gemini.fast_model', 'gemini-3.5-flash-lite');

        $service = app(GeminiTutorService::class);
        $this->lesson->forceFill([
            'ai_context_text' => 'Mục lục: 00:40 Định nghĩa đạo hàm.',
            'ai_context_hash' => $service->computeLessonHash($this->lesson),
            'ai_context_ready_at' => now(),
        ])->save();

        $ssePayload = implode("\n", [
            'event: interaction.created',
            'data: {"event_type":"interaction.created","interaction":{"id":"int_stream_1","status":"in_progress"}}',
            '',
            'event: step.delta',
            'data: {"event_type":"step.delta","delta":{"type":"text","text":"Tóm tắt: "}}',
            '',
            'event: step.delta',
            'data: {"event_type":"step.delta","delta":{"type":"text","text":"Mốc 00:40 giảng định nghĩa đạo hàm."}}',
            '',
            'event: interaction.completed',
            'data: {"event_type":"interaction.completed","interaction":{"id":"int_stream_1","status":"completed"}}',
            '',
            'event: done',
            'data: [DONE]',
            '',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1/interactions' => Http::response(
                $ssePayload,
                200,
                ['Content-Type' => 'text/event-stream']
            ),
        ]);

        $quickSummaryPrompt = 'Hãy tóm tắt các ý chính trong video bài giảng này kèm các mốc thời gian quan trọng (mm:ss).';

        // Lượt 1: Stream SSE từ Gemini + lưu cache gợi ý nhanh
        $streamRes1 = $this->actingAs($this->student)
            ->post(route('student.ai-tutor.stream', [$this->course, $this->lesson]), [
                'question' => $quickSummaryPrompt,
            ]);

        $streamRes1->assertOk();
        $content1 = $streamRes1->streamedContent();
        $this->assertStringContainsString('event: delta', $content1);
        $this->assertStringContainsString('Tóm tắt: ', $content1);
        $this->assertStringContainsString('Mốc 00:40 giảng định nghĩa đạo hàm.', $content1);
        $this->assertStringContainsString('event: done', $content1);

        // Lượt 2: Bấm lại nút gợi ý nhanh -> trúng cache tức thì, KHÔNG gọi thêm Gemini!
        $streamRes2 = $this->actingAs($this->student)
            ->post(route('student.ai-tutor.stream', [$this->course, $this->lesson]), [
                'question' => $quickSummaryPrompt,
            ]);

        $streamRes2->assertOk();
        $content2 = $streamRes2->streamedContent();
        $this->assertStringContainsString('"cached":true', $content2);
        $this->assertStringContainsString('Mốc 00:40 giảng định nghĩa đạo hàm.', $content2);

        Http::assertSentCount(1);
    }

    public function test_automatic_fallback_to_fast_model_when_primary_model_returns_503(): void
    {
        config()->set('services.gemini.api_key', 'test-api-key');
        config()->set('services.gemini.model', 'gemini-4-hydrogen');
        config()->set('services.gemini.fast_model', 'gemini-3.5-flash-lite');

        Http::fake([
            'https://generativelanguage.googleapis.com/v1/interactions' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => 'gemini-4-hydrogen is currently experiencing high demand',
                        'code' => 'service_unavailable',
                    ],
                ], 503)
                ->push([
                    'id' => 'int_fallback_ok',
                    'steps' => [
                        [
                            'type' => 'model_output',
                            'content' => [
                                ['type' => 'text', 'text' => 'Bản ghi chú đọc ngầm thành công từ model dự phòng.'],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $this->actingAs($this->student)
            ->postJson(route('student.ai-tutor.prepare', [$this->course, $this->lesson]))
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'ready', 'cached' => false]);

        $this->lesson->refresh();
        $this->assertStringContainsString('Bản ghi chú đọc ngầm thành công', (string) $this->lesson->ai_context_text);

        Http::assertSentCount(2);
        $recorded = Http::recorded();
        $this->assertSame('gemini-4-hydrogen', $recorded[0][0]->data()['model'] ?? null);
        $this->assertSame('gemini-3.5-flash-lite', $recorded[1][0]->data()['model'] ?? null);
    }

    public function test_ai_tutor_always_attaches_lesson_pdf_even_when_video_context_is_cached(): void
    {
        Storage::fake();
        Storage::put('documents/de-bai.pdf', '%PDF-1.4 fake pdf bytes');

        Document::create([
            'course_id' => $this->course->id,
            'lesson_id' => $this->lesson->id,
            'type' => 'file',
            'file_path' => 'documents/de-bai.pdf',
            'original_name' => 'de-bai.pdf',
            'mime_type' => 'application/pdf',
            'title' => 'Đề bài tập về nhà',
            'allow_view' => true,
            'allow_download' => true,
            'status' => 'approved',
            'approval_status' => 'approved',
            'gemini_file_uri' => 'https://generativelanguage.googleapis.com/v1beta/files/pdf123',
            'gemini_file_name' => 'files/pdf123',
            'gemini_file_expires_at' => now()->addHours(20),
        ]);

        config()->set('services.gemini.api_key', 'test-api-key');
        config()->set('services.gemini.model', 'gemini-3.1-flash-lite');
        config()->set('services.gemini.fast_model', 'gemini-3.1-flash-lite');

        $service = app(GeminiTutorService::class);
        $this->lesson->forceFill([
            'ai_context_text' => 'Ghi chú video: 01:00 Khái niệm đạo hàm.',
            'ai_context_hash' => $service->computeLessonHash($this->lesson),
            'ai_context_ready_at' => now(),
        ])->save();

        Http::fake([
            'https://generativelanguage.googleapis.com/v1/interactions' => Http::response([
                'id' => 'int_pdf_qa_1',
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [
                            ['type' => 'text', 'text' => 'Câu 1 trong file Đề bài tập về nhà yêu cầu tìm m để hàm số nghịch biến.'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($this->student)
            ->postJson(route('student.ai-tutor.ask', [$this->course, $this->lesson]), [
                'question' => 'Giải Câu 1 trong file tài liệu giúp mình',
            ])
            ->assertOk()
            ->assertJsonPath('assistant_message.content', 'Câu 1 trong file Đề bài tập về nhà yêu cầu tìm m để hàm số nghịch biến.');

        $recorded = Http::recorded();
        $qaContent = $recorded[0][0]->data()['input'][0]['content'] ?? [];

        // Không gửi lại video nặng, nhưng LUÔN đính kèm trực tiếp PDF để đọc chính xác 100% nội dung file!
        $this->assertFalse(collect($qaContent)->contains(fn ($p) => ($p['type'] ?? null) === 'video'));
        $this->assertTrue(collect($qaContent)->contains(fn ($p) => ($p['type'] ?? null) === 'document'
            && ($p['uri'] ?? null) === 'https://generativelanguage.googleapis.com/v1beta/files/pdf123'));
    }
}
