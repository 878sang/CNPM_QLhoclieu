<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        $roles = collect(['admin', 'teacher', 'student'])->mapWithKeys(fn (string $name) => [$name => Role::create(['name' => $name, 'display_name' => $name])]);
        $this->admin = User::factory()->create(['role_id' => $roles['admin']->id]);
        $this->teacher = User::factory()->create(['role_id' => $roles['teacher']->id]);
        $this->student = User::factory()->create(['role_id' => $roles['student']->id]);
        $this->course = Course::factory()->create(['teacher_id' => $this->teacher->id]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function storedDocument(array $attributes = []): Document
    {
        Storage::put('documents/old.pdf', 'old');

        return Document::factory()->create(['course_id' => $this->course->id, 'file_path' => 'documents/old.pdf', ...$attributes]);
    }

    private function upload(string $filename, string $mime): void
    {
        $this->post(route('courses.documents.store', $this->course), ['title' => 'Bài tập 1', 'file' => UploadedFile::fake()->create($filename, 50, $mime), 'allow_view' => '1']);
    }

    public function test_teacher_upload_is_pending_and_admin_upload_is_auto_approved(): void
    {
        $this->actingAs($this->teacher);
        $this->upload('bai.pdf', 'application/pdf');
        $this->assertSame(Document::STATUS_PENDING, Document::firstOrFail()->approval_status);

        Document::query()->delete();
        $this->actingAs($this->admin);
        $this->upload('bai.pdf', 'application/pdf');
        $document = Document::firstOrFail();
        $this->assertSame(Document::STATUS_APPROVED, $document->approval_status);
        $this->assertSame($this->admin->id, $document->reviewed_by);
    }

    public function test_word_upload_is_converted_to_pdf(): void
    {
        Process::fake(function ($process) {
            $outDir = $process->command[array_search('--outdir', $process->command) + 1];
            $source = end($process->command);
            file_put_contents($outDir.DIRECTORY_SEPARATOR.pathinfo($source, PATHINFO_FILENAME).'.pdf', '%PDF-fake');

            return Process::result();
        });

        $this->actingAs($this->teacher);
        $this->upload('bai.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $document = Document::firstOrFail();
        $this->assertSame('converted', $document->conversion_status);
        Storage::assertExists($document->pdf_path);
        $this->assertSame('application/pdf', $document->viewableFile()['mime']);
        $this->assertStringEndsWith('.pdf', $document->viewableFile()['name']);
    }

    public function test_failed_conversion_keeps_original_and_marks_failed(): void
    {
        Process::fake(fn () => Process::result(errorOutput: 'boom', exitCode: 1));

        $this->actingAs($this->teacher);
        $this->upload('bai.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation');

        $document = Document::firstOrFail();
        $this->assertSame('failed', $document->conversion_status);
        $this->assertNull($document->pdf_path);
        Storage::assertExists($document->file_path);
    }

    public function test_pdf_upload_is_not_converted(): void
    {
        Process::fake();

        $this->actingAs($this->teacher);
        $this->upload('bai.pdf', 'application/pdf');

        Process::assertNothingRan();
        $this->assertSame('not_needed', Document::firstOrFail()->conversion_status);
    }

    public function test_replacing_file_resets_review_and_removes_old_pdf(): void
    {
        Storage::put('documents/pdf/old.pdf', 'old-pdf');
        $document = $this->storedDocument(['pdf_path' => 'documents/pdf/old.pdf', 'conversion_status' => 'converted']);

        $this->actingAs($this->teacher)->put(route('courses.documents.update', [$this->course, $document]), ['title' => 'Mới', 'file' => UploadedFile::fake()->create('moi.pdf', 10, 'application/pdf')]);

        $document->refresh();
        $this->assertSame(Document::STATUS_PENDING, $document->approval_status);
        $this->assertNull($document->pdf_path);
        Storage::assertMissing('documents/pdf/old.pdf');
    }

    public function test_admin_sees_pending_list_with_teacher_and_course(): void
    {
        $pending = $this->storedDocument(['approval_status' => Document::STATUS_PENDING, 'title' => 'Bài chờ duyệt']);
        $this->storedDocument(['title' => 'Bài đã duyệt']);

        $this->actingAs($this->admin)->get(route('admin.documents.index'))
            ->assertOk()
            ->assertSee('Bài chờ duyệt')
            ->assertSee($this->teacher->name)
            ->assertSee($this->course->title)
            ->assertDontSee('Bài đã duyệt');

        $this->get(route('admin.documents.index', ['status' => 'all']))->assertSee('Bài đã duyệt')->assertSee($pending->title);
    }

    public function test_admin_approves_document(): void
    {
        $document = $this->storedDocument(['approval_status' => Document::STATUS_REJECTED, 'rejection_reason' => 'cũ']);

        $this->actingAs($this->admin)->post(route('admin.documents.approve', $document))->assertRedirect();

        $document->refresh();
        $this->assertSame(Document::STATUS_APPROVED, $document->approval_status);
        $this->assertNull($document->rejection_reason);
        $this->assertSame($this->admin->id, $document->reviewed_by);
        $this->assertNotNull($document->reviewed_at);
    }

    public function test_admin_rejects_document_only_with_reason(): void
    {
        $document = $this->storedDocument(['approval_status' => Document::STATUS_PENDING]);
        $this->actingAs($this->admin);

        $this->post(route('admin.documents.reject', $document), ['rejection_reason' => ''])->assertSessionHasErrors('rejection_reason');
        $this->assertSame(Document::STATUS_PENDING, $document->fresh()->approval_status);

        $this->post(route('admin.documents.reject', $document), ['rejection_reason' => 'Sai đề cương'])->assertRedirect();

        $document->refresh();
        $this->assertSame(Document::STATUS_REJECTED, $document->approval_status);
        $this->assertSame('Sai đề cương', $document->rejection_reason);
    }

    public function test_teacher_sees_rejection_reason(): void
    {
        $this->storedDocument(['approval_status' => Document::STATUS_REJECTED, 'rejection_reason' => 'Thiếu đáp án']);

        $this->actingAs($this->teacher)->get(route('courses.documents.index', $this->course))->assertSee('Bị từ chối')->assertSee('Thiếu đáp án');
    }

    public function test_teacher_and_student_cannot_review(): void
    {
        $document = $this->storedDocument(['approval_status' => Document::STATUS_PENDING]);

        foreach ([$this->teacher, $this->student] as $user) {
            $this->actingAs($user)->get(route('admin.documents.index'))->assertForbidden();
            $this->post(route('admin.documents.approve', $document))->assertForbidden();
            $this->post(route('admin.documents.reject', $document), ['rejection_reason' => 'x'])->assertForbidden();
        }

        $this->assertSame(Document::STATUS_PENDING, $document->fresh()->approval_status);
    }

    public function test_student_only_accesses_approved_documents(): void
    {
        Enrollment::factory()->create(['course_id' => $this->course->id, 'user_id' => $this->student->id]);
        $approved = $this->storedDocument(['title' => 'Đã duyệt', 'allow_download' => true]);
        $pending = $this->storedDocument(['title' => 'Chờ duyệt', 'approval_status' => Document::STATUS_PENDING, 'allow_download' => true]);
        $rejected = $this->storedDocument(['title' => 'Bị từ chối', 'approval_status' => Document::STATUS_REJECTED, 'allow_download' => true]);
        $this->actingAs($this->student);

        $this->get(route('student.courses.show', $this->course))->assertOk()->assertSee('Đã duyệt')->assertDontSee('Chờ duyệt')->assertDontSee('Bị từ chối');
        $this->get(route('courses.documents.show', [$this->course, $approved]))->assertOk();
        $this->get(route('courses.documents.download', [$this->course, $approved]))->assertOk();

        foreach ([$pending, $rejected] as $document) {
            $this->get(route('courses.documents.show', [$this->course, $document]))->assertForbidden();
            $this->get(route('courses.documents.download', [$this->course, $document]))->assertForbidden();
        }
    }

    public function test_student_views_converted_pdf_but_downloads_original(): void
    {
        Enrollment::factory()->create(['course_id' => $this->course->id, 'user_id' => $this->student->id]);
        Storage::put('documents/pdf/new.pdf', '%PDF-converted');
        $document = $this->storedDocument(['pdf_path' => 'documents/pdf/new.pdf', 'original_name' => 'bai.docx', 'mime_type' => 'application/msword', 'allow_download' => true]);
        $this->actingAs($this->student);

        $this->get(route('courses.documents.show', [$this->course, $document]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('courses.documents.download', [$this->course, $document]))->assertOk()->assertDownload('bai.docx');
    }

    public function test_teacher_manages_only_documents_of_own_courses_with_filters(): void
    {
        $otherCourse = Course::factory()->create();
        $this->storedDocument(['title' => 'Bài của tôi']);
        $this->storedDocument(['title' => 'Bài chờ', 'approval_status' => Document::STATUS_PENDING]);
        Document::factory()->create(['course_id' => $otherCourse->id, 'title' => 'Bài người khác']);
        $this->actingAs($this->teacher);

        $this->get(route('teacher.materials'))->assertOk()->assertSee('Bài của tôi')->assertSee('Bài chờ')->assertDontSee('Bài người khác');
        $this->get(route('teacher.materials', ['status' => 'pending']))->assertSee('Bài chờ')->assertDontSee('Bài của tôi');
        $this->get(route('teacher.materials', ['q' => 'của tôi']))->assertSee('Bài của tôi')->assertDontSee('Bài chờ');
        $this->get(route('teacher.materials', ['course_id' => $otherCourse->id]))->assertDontSee('Bài của tôi');
    }

    public function test_only_teachers_open_the_manage_page(): void
    {
        $this->actingAs($this->student)->get(route('teacher.materials'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('teacher.materials'))->assertForbidden();
    }

    public function test_course_detail_hides_documents_from_unenrolled_student(): void
    {
        $course = Course::factory()->create(['status' => 'public']);
        Document::factory()->create(['course_id' => $course->id, 'title' => 'Bí mật']);

        $this->actingAs($this->student)->get(route('student.courses.show', $course))->assertOk()->assertDontSee('Bí mật');
    }
}
