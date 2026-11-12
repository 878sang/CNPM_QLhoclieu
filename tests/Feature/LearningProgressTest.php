<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Document;
use App\Models\DocumentProgress;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LearningProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        $roles = collect(['admin', 'teacher', 'student'])->mapWithKeys(fn (string $name) => [$name => Role::create(['name' => $name, 'display_name' => $name])]);
        $this->teacher = User::factory()->create(['role_id' => $roles['teacher']->id]);
        $this->student = User::factory()->create(['role_id' => $roles['student']->id]);
        $this->course = Course::factory()->create(['teacher_id' => $this->teacher->id]);
        Enrollment::factory()->create(['course_id' => $this->course->id, 'user_id' => $this->student->id]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function video(array $attributes = []): Document
    {
        Storage::put('documents/video.mp4', '0123456789');

        return Document::factory()->create([
            'course_id' => $this->course->id,
            'title' => 'Video bài 1',
            'file_path' => 'documents/video.mp4',
            'original_name' => 'video.mp4',
            'mime_type' => 'video/mp4',
            'allow_view' => true,
            'allow_download' => false,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pdf(array $attributes = []): Document
    {
        Storage::put('documents/tai-lieu.pdf', '%PDF');

        return Document::factory()->create(['course_id' => $this->course->id, 'file_path' => 'documents/tai-lieu.pdf', ...$attributes]);
    }

    private function report(Document $document, int $position, int $duration = 100): TestResponse
    {
        return $this->postJson(route('student.documents.progress', $document), ['position' => $position, 'duration' => $duration]);
    }

    public function test_video_progress_is_recorded_with_resume_position(): void
    {
        $video = $this->video();

        $this->actingAs($this->student);
        $this->report($video, 40)->assertOk()->assertJson(['percent' => 40, 'position' => 40, 'completed' => false]);

        $this->assertDatabaseHas('document_progress', ['user_id' => $this->student->id, 'document_id' => $video->id, 'position_seconds' => 40, 'duration_seconds' => 100, 'percent' => 40]);
    }

    public function test_percent_never_drops_when_student_rewinds(): void
    {
        $video = $this->video();
        $this->actingAs($this->student);

        $this->report($video, 60);
        $this->report($video, 10)->assertOk()->assertJson(['percent' => 60, 'position' => 10]);

        $this->assertDatabaseCount('document_progress', 1);
    }

    public function test_watching_ninety_percent_completes_the_video(): void
    {
        $video = $this->video();
        $this->actingAs($this->student);

        $this->report($video, 89)->assertJson(['completed' => false]);
        $this->report($video, 90)->assertJson(['completed' => true]);

        $this->assertNotNull(DocumentProgress::firstOrFail()->completed_at);
    }

    public function test_position_beyond_duration_is_clamped(): void
    {
        $this->actingAs($this->student);

        $this->report($this->video(), 500, 100)->assertOk()->assertJson(['percent' => 100, 'position' => 100]);
    }

    public function test_progress_payload_is_validated(): void
    {
        $video = $this->video();
        $this->actingAs($this->student);

        $this->postJson(route('student.documents.progress', $video), [])->assertJsonValidationErrors(['position', 'duration']);
        $this->report($video, -1)->assertJsonValidationErrors('position');
        $this->report($video, 5, 0)->assertJsonValidationErrors('duration');
    }

    public function test_progress_is_rejected_when_student_has_no_access(): void
    {
        $outsider = User::factory()->create(['role_id' => $this->student->role_id]);
        $video = $this->video();

        $this->actingAs($outsider);
        $this->report($video, 10)->assertForbidden();

        $this->actingAs($this->student);
        $this->report($this->video(['approval_status' => Document::STATUS_PENDING]), 10)->assertForbidden();
        $this->report($this->video(['allow_view' => false]), 10)->assertForbidden();
        $this->report($this->pdf(), 10)->assertForbidden();

        $this->actingAs($this->teacher);
        $this->report($video, 10)->assertForbidden();

        $this->assertDatabaseCount('document_progress', 0);
    }

    public function test_video_streams_with_range_support(): void
    {
        $video = $this->video();

        $this->actingAs($this->student)
            ->get(route('courses.documents.show', [$this->course, $video]), ['Range' => 'bytes=0-3'])
            ->assertStatus(206)
            ->assertHeader('Content-Type', 'video/mp4');
    }

    public function test_viewing_a_non_video_document_marks_it_completed_for_students_only(): void
    {
        $document = $this->pdf();

        $this->actingAs($this->teacher)->get(route('courses.documents.show', [$this->course, $document]))->assertOk();
        $this->assertDatabaseCount('document_progress', 0);

        $this->actingAs($this->student)->get(route('courses.documents.show', [$this->course, $document]))->assertOk();
        $this->assertDatabaseHas('document_progress', ['user_id' => $this->student->id, 'document_id' => $document->id, 'percent' => 100]);
        $this->assertNotNull(DocumentProgress::firstOrFail()->completed_at);
    }

    public function test_streaming_a_video_does_not_mark_it_completed(): void
    {
        $this->actingAs($this->student)->get(route('courses.documents.show', [$this->course, $this->video()]))->assertOk();

        $this->assertDatabaseCount('document_progress', 0);
    }

    public function test_course_detail_shows_player_and_download_button_by_permission(): void
    {
        $this->video(['title' => 'Chỉ xem', 'allow_download' => false]);
        $this->video(['title' => 'Xem và tải', 'allow_download' => true]);
        $this->video(['title' => 'Chỉ tải', 'allow_view' => false, 'allow_download' => true]);

        $html = $this->actingAs($this->student)->get(route('student.courses.show', $this->course))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, '<video'));
        $this->assertSame(1, substr_count($html, 'controlsList="nodownload"'));
        $this->assertSame(2, substr_count($html, 'Tải video'));
    }

    public function test_course_detail_shows_resume_position_and_percent(): void
    {
        $video = $this->video();
        DocumentProgress::factory()->create(['user_id' => $this->student->id, 'document_id' => $video->id, 'position_seconds' => 30, 'duration_seconds' => 100, 'percent' => 30]);

        $this->actingAs($this->student)->get(route('student.courses.show', $this->course))
            ->assertSee('data-resume="30"', false)
            ->assertSee('Đã xem 30%');
    }

    public function test_course_progress_counts_completed_over_trackable_documents(): void
    {
        $done = $this->pdf(['title' => 'Đã xong']);
        $this->pdf(['title' => 'Chưa xong']);
        $this->pdf(['title' => 'Không tính vì chưa duyệt', 'approval_status' => Document::STATUS_PENDING]);
        $this->pdf(['title' => 'Không tính vì chỉ tải', 'allow_view' => false, 'allow_download' => true]);
        DocumentProgress::factory()->completed()->create(['user_id' => $this->student->id, 'document_id' => $done->id]);

        $course = Course::withProgressFor($this->student)->findOrFail($this->course->id);
        $this->assertSame(2, (int) $course->trackable_documents_count);
        $this->assertSame(1, (int) $course->completed_documents_count);
        $this->assertSame(50, $course->progress_percent);

        $this->actingAs($this->student)->get(route('student.progress'))->assertOk()->assertSee('50% · 1/2 học liệu')->assertSee('Đang học');
        $this->get(route('student.courses'))->assertSee('50% · 1/2 học liệu đã hoàn thành');
        $this->get(route('student.courses.show', $this->course))->assertSee('50% hoàn thành');
    }

    public function test_progress_is_per_student(): void
    {
        $other = User::factory()->create(['role_id' => $this->student->role_id]);
        Enrollment::factory()->create(['course_id' => $this->course->id, 'user_id' => $other->id]);
        $document = $this->pdf();
        DocumentProgress::factory()->completed()->create(['user_id' => $other->id, 'document_id' => $document->id]);

        $this->assertSame(0, Course::withProgressFor($this->student)->findOrFail($this->course->id)->progress_percent);
        $this->assertSame(100, Course::withProgressFor($other)->findOrFail($this->course->id)->progress_percent);
    }

    public function test_course_without_documents_has_zero_progress(): void
    {
        $this->actingAs($this->student)->get(route('student.progress'))->assertOk()->assertSee('Chưa bắt đầu')->assertSee('Khóa học chưa có học liệu');
    }

    public function test_teacher_can_upload_mp4_and_oversized_video_is_rejected(): void
    {
        $this->actingAs($this->teacher);
        $payload = fn (UploadedFile $file) => ['title' => 'Video', 'file' => $file, 'allow_view' => '1'];

        $this->post(route('courses.documents.store', $this->course), $payload(UploadedFile::fake()->create('bai.mp4', 30000, 'video/mp4')))->assertSessionHasNoErrors();
        $this->assertTrue(Document::firstOrFail()->isVideo());

        $this->post(route('courses.documents.store', $this->course), $payload(UploadedFile::fake()->create('lon.mp4', 204801, 'video/mp4')))->assertSessionHasErrors('file');
        $this->post(route('courses.documents.store', $this->course), $payload(UploadedFile::fake()->create('bai.avi', 100, 'video/x-msvideo')))->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 1);
    }
}
