<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'student', string $password = 'password'): User
    {
        $r = Role::create(['name' => $role, 'display_name' => $role]);

        return User::factory()->create(['role_id' => $r->id, 'password' => $password]);
    }

    public function test_user_can_login_with_username_or_email(): void
    {
        $u = $this->user();
        $this->post(route('login.store'), ['login' => $u->username, 'password' => 'password'])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_invalid_login_is_rejected(): void
    {
        $this->user();
        $this->post(route('login.store'), ['login' => 'wrong', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_locked_account_is_rejected(): void
    {
        $u = $this->user();
        $u->update(['status' => 'locked']);
        $this->post(route('login.store'), ['login' => $u->email, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_role_middleware_forbids_wrong_role(): void
    {
        $this->actingAs($this->user('student'))->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs(User::first())->get(route('student.dashboard'))->assertOk();
    }

    public function test_each_role_can_access_only_its_workspace(): void
    {
        $roles = collect(['admin', 'teacher', 'student'])->mapWithKeys(fn (string $name) => [$name => Role::create(['name' => $name, 'display_name' => $name])]);
        foreach ($roles as $name => $role) {
            $user = User::factory()->create(['role_id' => $role->id]);
            $route = route(match ($name) {
                'admin' => 'admin.dashboard', 'teacher' => 'teacher.dashboard', default => 'student.dashboard'
            });
            $this->actingAs($user)->get($route)->assertOk();
        }
        $this->actingAs(User::where('role_id', $roles['student']->id)->first())->get(route('teacher.dashboard'))->assertForbidden();
    }

    public function test_course_and_user_policies_enforce_ownership_and_admin_access(): void
    {
        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'teacher']);
        $adminRole = Role::create(['name' => 'admin', 'display_name' => 'admin']);
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'student']);
        $teacher = User::factory()->create(['role_id' => $teacherRole->id]);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $student = User::factory()->create(['role_id' => $studentRole->id]);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => Subject::factory()]);

        $this->assertTrue($teacher->can('update', $course));
        $this->assertFalse($student->can('update', $course));
        $this->assertTrue($admin->can('update', $course));
        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertFalse($student->can('viewAny', User::class));
    }

    public function test_admin_can_open_dashboard_and_create_an_account(): void
    {
        $adminRole = Role::create(['name' => 'admin', 'display_name' => 'Quản trị viên']);
        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Giảng viên']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Giảng viên mới',
            'username' => 'giangvienmoi',
            'email' => 'moi@example.com',
            'role_id' => $teacherRole->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 'active',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['username' => 'giangvienmoi', 'role_id' => $teacherRole->id]);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $u = $this->user();
        $this->actingAs($u)->put(route('password.change'), ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password', $u->refresh()->password));
    }

    public function test_google_login_links_existing_account(): void
    {
        $u = $this->user();
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn(new class
        {
            public function getEmail(): string
            {
                return 'student@example.com';
            }

            public function getId(): string
            {
                return 'google-123';
            }
        });
        $u->update(['email' => 'student@example.com']);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
        $this->get(route('google.callback'))->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($u);
        $this->assertSame('google-123', $u->refresh()->google_id);
    }

    public function test_forgot_password_page_accepts_existing_email(): void
    {
        $u = $this->user();
        $response = $this->post(route('password.email'), ['email' => $u->email]);
        $response->assertSessionHas('status');
    }
}
