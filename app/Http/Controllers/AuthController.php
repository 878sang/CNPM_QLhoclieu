<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class AuthController extends Controller
{
    private function roleHome(User $user): string
    {
        return route($user->hasRole('admin') ? 'admin.dashboard' : ($user->hasRole('teacher') ? 'teacher.dashboard' : 'student.dashboard'));
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $user = User::where('username', $request->login)->orWhere('email', $request->login)->first();

        if ($user?->status === 'locked') {
            return back()->withErrors(['login' => 'Tài khoản đã bị khóa, vui lòng liên hệ quản trị viên.'])->onlyInput('login');
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['login' => 'Thông tin đăng nhập không chính xác.'])->onlyInput('login');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended($this->roleHome($user));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function redirectGoogle(): SymfonyRedirectResponse|RedirectResponse
    {
        /** @var SocialiteProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->redirect();
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        /** @var SocialiteProvider $driver */
        $driver = Socialite::driver('google');
        $google = $driver->user();

        $user = User::where('email', $google->getEmail())->first();

        if (! $user) {
            return redirect()->route('login')->withErrors(['login' => 'Tài khoản chưa được cấp quyền, vui lòng liên hệ quản trị viên.']);
        }

        if ($user->status === 'locked') {
            return redirect()->route('login')->withErrors(['login' => 'Tài khoản đã bị khóa, vui lòng liên hệ quản trị viên.']);
        }

        $user->forceFill(['google_id' => $google->getId(), 'email_verified_at' => $user->email_verified_at ?: now()])->save();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended($this->roleHome($user));
    }

    public function editPassword(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $layout = match ($user->role?->name) {
            'admin' => 'layouts.admin',
            'teacher' => 'layouts.teacher',
            default => 'layouts.student',
        };

        return view('auth.password', compact('layout'));
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->password]);

        return back()->with('status', 'Đổi mật khẩu thành công.');
    }

    public function showForgot(): View
    {
        return view('auth.forgot-password');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email'], ['email.required' => 'Vui lòng nhập email.', 'email.email' => 'Email không hợp lệ.']);
        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', 'Liên kết đặt lại mật khẩu đã được gửi đến email của bạn.')
            : back()->withErrors(['email' => 'Không thể gửi liên kết đặt lại mật khẩu.']);
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->email]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ], [
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ]);

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill(['password' => $password])->save();
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Đặt lại mật khẩu thành công.')
            : back()->withErrors(['email' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.']);
    }
}
