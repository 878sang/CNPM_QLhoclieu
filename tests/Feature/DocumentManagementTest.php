<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $otherTeacher;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        $roles = collect(['admin', 'teacher', 'student'])->mapWithKeys(fn (string $name) => [$name => Role::create(['name' => $name, 'display_name' => $name])]);
        $this->teacher = User::factory()->create(['role_id' => $roles['teacher']->id]);
        $this->otherTeacher = User::factory()->create(['role_id' => $roles['teacher']->id]);
        $this->student = User::factory()->create(['role_id' => $roles['student']->id]);
        $this->course = Course::factory()->create(['teacher_id' => $this->teacher->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [...['title' => 'Slide tuần 1', 'file' => UploadedFile::fake()->create('slide.pdf', 100, 'application/pdf'), 'allow_view' => '1'], ...$overrides];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function storedDocument(array $attributes = []): Document
    {
        Storage::put('documents/old.pdf', 'old');

        return Document::factory()->create(['course_id' => $this->course->id, 'file_path' => 'documents/old.pdf', ...$attributes]);
    }

    public function test_teacher_uploads_document_attached_to_lesson_with_permissions(): void
    {
        $lesson = Lesson::factory()->create(['chapter_id' => Chapter::factory()->create(['course_id' => $this->course->id])->id]);

        $this->actingAs($this->teacher)->get(route('courses.documents.index', $this->course))->assertOk();
        $this->post(route('courses.documents.store', $this->course), $this->payload(['lesson_id' => $lesson->id, 'allow_download' => '1']))
            ->assertRedirect(route('courses.documents.index', $this->course));

        $document = Document::firstOrFail();
        $this->assertSame($lesson->id, $document->lesson_id);
        $this->assertTrue($document->allow_view);
        $this->assertTrue($document->allow_download);
        Storage::assertExists($document->file_path);
    }

    public function test_upload_rejects_bad_type_oversize_missing_file_and_foreign_lesson(): void
    {
        $foreignLesson = Lesson::factory()->create();
        $this->actingAs($this->teacher);

        $this->post(route('courses.documents.store', $this->course), $this->payload(['file' => UploadedFile::fake()->create('virus.exe', 10)]))->assertSessionHasErrors('file');
        $this->post(route('courses.documents.store', $this->course), $this->payload(['file' => UploadedFile::fake()->create('big.pdf', 20481, 'application/pdf')]))->assertSessionHasErrors('file');
        $this->post(route('courses.documents.store', $this->course), ['title' => 'Không tệp'])->assertSessionHasErrors('file');
        $this->post(route('courses.documents.store', $this->course), $this->payload(['lesson_id' => $foreignLesson->id]))->assertSessionHasErrors('lesson_id');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_update_changes_info_and_flags_without_touching_file(): void
    {
        $document = $this->storedDocument(['allow_view' => true, 'allow_download' => true]);

        $this->actingAs($this->teacher)->put(route('courses.documents.update', [$this->course, $document]), ['title' => 'Tên mới'])
            ->assertRedirect(route('courses.documents.index', $this->course));

        $document->refresh();
        $this->assertSame('Tên mới', $document->title);
        $this->assertFalse($document->allow_view);
        $this->assertFalse($document->allow_download);
        $this->assertSame('documents/old.pdf', $document->file_path);
        Storage::assertExists('documents/old.pdf');
    }

    public function test_replacing_file_deletes_old_file(): void
    {
        $document = $this->storedDocument();

        $this->actingAs($this->teacher)->put(route('courses.documents.update', [$this->course, $document]), $this->payload(['file' => UploadedFile::fake()->create('moi.pdf', 10, 'application/pdf')]));

        $document->refresh();
        $this->assertSame('moi.pdf', $document->original_name);
        Storage::assertMissing('documents/old.pdf');
        Storage::assertExists($document->file_path);
    }

    public function test_destroy_removes_record_and_file(): void
    {
        $document = $this->storedDocument();

        $this->actingAs($this->teacher)->delete(route('courses.documents.destroy', [$this->course, $document]))->assertRedirect();

        $this->assertModelMissing($document);
        Storage::assertMissing('documents/old.pdf');
    }

    public function test_deleting_lesson_keeps_document_unattached(): void
    {
        $lesson = Lesson::factory()->create(['chapter_id' => Chapter::factory()->create(['course_id' => $this->course->id])->id]);
        $document = $this->storedDocument(['lesson_id' => $lesson->id]);

        $lesson->delete();

        $this->assertNull($document->fresh()->lesson_id);
    }

    public function test_other_teacher_and_student_cannot_manage_documents(): void
    {
        $document = $this->storedDocument();

        $this->actingAs($this->otherTeacher)->get(route('courses.documents.index', $this->course))->assertForbidden();
        $this->post(route('courses.documents.store', $this->course), $this->payload())->assertForbidden();
        $this->put(route('courses.documents.update', [$this->course, $document]), ['title' => 'x'])->assertForbidden();
        $this->delete(route('courses.documents.destroy', [$this->course, $document]))->assertForbidden();
        $this->actingAs($this->student)->get(route('courses.documents.index', $this->course))->assertForbidden();
    }

    public function test_document_from_another_course_is_not_found_under_this_course(): void
    {
        $document = Document::factory()->create();

        $this->actingAs($this->teacher)->get(route('courses.documents.edit', [$this->course, $document]))->assertNotFound();
    }

    public function test_enrolled_student_view_and_download_follow_flags(): void
    {
        Enrollment::factory()->create(['course_id' => $this->course->id, 'user_id' => $this->student->id]);
        $viewOnly = $this->storedDocument(['allow_view' => true, 'allow_download' => false]);
        $downloadOnly = $this->storedDocument(['allow_view' => false, 'allow_download' => true]);
        $this->actingAs($this->student);

        $this->get(route('courses.documents.show', [$this->course, $viewOnly]))->assertOk();
        $this->get(route('courses.documents.download', [$this->course, $viewOnly]))->assertForbidden();
        $this->get(route('courses.documents.show', [$this->course, $downloadOnly]))->assertForbidden();
        $this->get(route('courses.documents.download', [$this->course, $downloadOnly]))->assertOk();
    }

    public function test_unenrolled_student_cannot_view_but_owner_always_can(): void
    {
        $document = $this->storedDocument(['allow_view' => true, 'allow_download' => false]);

        $this->actingAs($this->student)->get(route('courses.documents.show', [$this->course, $document]))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('courses.documents.download', [$this->course, $document]))->assertOk();
    }
}
