<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $otherTeacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $roles = collect(['admin', 'teacher', 'student'])->mapWithKeys(fn (string $name) => [$name => Role::create(['name' => $name, 'display_name' => $name])]);
        $this->admin = User::factory()->create(['role_id' => $roles['admin']->id]);
        $this->teacher = User::factory()->create(['role_id' => $roles['teacher']->id]);
        $this->otherTeacher = User::factory()->create(['role_id' => $roles['teacher']->id]);
        $this->student = User::factory()->create(['role_id' => $roles['student']->id]);
    }

    private function course(): Course
    {
        return Course::factory()->create(['teacher_id' => $this->teacher->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [...['subject_id' => Subject::factory()->create()->id, 'title' => 'Lập trình Web', 'description' => 'Mô tả', 'status' => 'public'], ...$overrides];
    }

    public function test_teacher_creates_course_owned_by_themselves_even_when_posting_another_teacher(): void
    {
        $this->actingAs($this->teacher)->get(route('courses.create'))->assertOk();
        $this->post(route('courses.store'), $this->payload(['teacher_id' => $this->otherTeacher->id]))->assertRedirect(route('courses.index'));

        $this->assertDatabaseHas('courses', ['title' => 'Lập trình Web', 'teacher_id' => $this->teacher->id, 'status' => 'public']);
    }

    public function test_admin_must_assign_a_real_teacher(): void
    {
        $this->actingAs($this->admin)->post(route('courses.store'), $this->payload())->assertSessionHasErrors('teacher_id');
        $this->post(route('courses.store'), $this->payload(['teacher_id' => $this->student->id]))->assertSessionHasErrors('teacher_id');
        $this->post(route('courses.store'), $this->payload(['teacher_id' => $this->otherTeacher->id]))->assertRedirect(route('courses.index'));

        $this->assertDatabaseCount('courses', 1);
        $this->assertDatabaseHas('courses', ['teacher_id' => $this->otherTeacher->id]);
    }

    public function test_course_form_rejects_invalid_input(): void
    {
        $this->actingAs($this->teacher)->post(route('courses.store'), [
            'subject_id' => 999,
            'title' => '',
            'status' => 'unknown',
            'cover_image' => UploadedFile::fake()->create('cover.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors(['subject_id', 'title', 'status', 'cover_image']);

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_updating_cover_image_stores_new_file_and_removes_old_one(): void
    {
        Storage::fake('public');
        $this->actingAs($this->teacher)->post(route('courses.store'), $this->payload(['cover_image' => UploadedFile::fake()->create('old.jpg', 100, 'image/jpeg')]));
        $course = Course::first();
        $oldCover = $course->cover_image;

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $disk->assertExists($oldCover);

        $this->get(route('courses.edit', $course))->assertOk()->assertSee($course->title);
        $this->put(route('courses.update', $course), $this->payload(['title' => 'Tên mới', 'cover_image' => UploadedFile::fake()->create('new.png', 100, 'image/png')]))->assertRedirect(route('courses.index'));

        $course->refresh();
        $this->assertSame('Tên mới', $course->title);
        $this->assertNotSame($oldCover, $course->cover_image);
        $disk->assertMissing($oldCover);
        $disk->assertExists($course->cover_image);
    }

    public function test_updating_without_new_cover_keeps_existing_one(): void
    {
        $course = Course::factory()->create(['teacher_id' => $this->teacher->id, 'cover_image' => 'courses/keep.jpg']);

        $this->actingAs($this->teacher)->put(route('courses.update', $course), $this->payload());

        $this->assertSame('courses/keep.jpg', $course->refresh()->cover_image);
    }

    public function test_teacher_cannot_reassign_course_to_another_teacher(): void
    {
        $course = $this->course();

        $this->actingAs($this->teacher)->put(route('courses.update', $course), $this->payload(['teacher_id' => $this->otherTeacher->id]));

        $this->assertSame($this->teacher->id, $course->refresh()->teacher_id);
    }

    public function test_non_owner_cannot_edit_course_or_manage_students(): void
    {
        $course = $this->course();
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id, 'user_id' => $this->student->id]);
        $this->actingAs($this->otherTeacher);

        $this->get(route('courses.edit', $course))->assertForbidden();
        $this->put(route('courses.update', $course), $this->payload())->assertForbidden();
        $this->get(route('courses.students.index', $course))->assertForbidden();
        $this->post(route('courses.students.store', $course), ['user_id' => $this->student->id])->assertForbidden();
        $this->delete(route('courses.students.destroy', [$course, $enrollment]))->assertForbidden();

        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
    }

    public function test_student_cannot_access_course_management(): void
    {
        $course = $this->course();
        $this->actingAs($this->student);

        $this->get(route('courses.index'))->assertForbidden();
        $this->get(route('courses.create'))->assertForbidden();
        $this->get(route('courses.students.index', $course))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('courses.students.index', $this->course()))->assertRedirect(route('login'));
    }

    public function test_teacher_list_shows_only_own_courses_and_admin_sees_all(): void
    {
        $own = $this->course();
        $other = Course::factory()->create(['teacher_id' => $this->otherTeacher->id]);

        $this->actingAs($this->teacher)->get(route('courses.index'))->assertSee($own->title)->assertDontSee($other->title);
        $this->actingAs($this->admin)->get(route('courses.index'))->assertSee($own->title)->assertSee($other->title);
    }

    public function test_owner_adds_student_and_page_lists_only_remaining_candidates(): void
    {
        $course = $this->course();
        $waiting = User::factory()->create(['role_id' => $this->student->role_id]);
        $this->actingAs($this->teacher);

        $this->post(route('courses.students.store', $course), ['user_id' => $this->student->id])->assertRedirect(route('courses.students.index', $course));

        $this->assertDatabaseHas('enrollments', ['course_id' => $course->id, 'user_id' => $this->student->id, 'status' => 'active']);
        $response = $this->get(route('courses.students.index', $course))->assertOk();
        $this->assertTrue($response->viewData('enrollments')->contains('user_id', $this->student->id));
        $this->assertSame([$waiting->id], $response->viewData('availableStudents')->pluck('id')->all());
    }

    public function test_cannot_add_duplicate_locked_or_non_student_user(): void
    {
        $course = $this->course();
        Enrollment::factory()->create(['course_id' => $course->id, 'user_id' => $this->student->id]);
        $locked = User::factory()->locked()->create(['role_id' => $this->student->role_id]);
        $this->actingAs($this->teacher);

        foreach ([$this->student->id, $locked->id, $this->otherTeacher->id, 999999, null] as $userId) {
            $this->post(route('courses.students.store', $course), ['user_id' => $userId])->assertSessionHasErrors('user_id');
        }

        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_owner_removes_student_from_course(): void
    {
        $course = $this->course();
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id, 'user_id' => $this->student->id]);

        $this->actingAs($this->teacher)->delete(route('courses.students.destroy', [$course, $enrollment]))->assertRedirect(route('courses.students.index', $course));

        $this->assertDatabaseMissing('enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseHas('users', ['id' => $this->student->id]);
    }

    public function test_enrollment_of_another_course_cannot_be_removed_through_own_course(): void
    {
        $foreign = Enrollment::factory()->create([
            'course_id' => Course::factory()->create(['teacher_id' => $this->otherTeacher->id])->id,
            'user_id' => $this->student->id,
        ]);

        $this->actingAs($this->teacher)->delete(route('courses.students.destroy', [$this->course(), $foreign]))->assertNotFound();

        $this->assertDatabaseHas('enrollments', ['id' => $foreign->id]);
    }

    public function test_admin_account_pages_render(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.users.create'))->assertOk();
    }

    public function test_admin_can_manage_students_of_any_course(): void
    {
        $course = $this->course();

        $this->actingAs($this->admin)->post(route('courses.students.store', $course), ['user_id' => $this->student->id])->assertRedirect();

        $this->assertDatabaseHas('enrollments', ['course_id' => $course->id, 'user_id' => $this->student->id]);
    }
}
