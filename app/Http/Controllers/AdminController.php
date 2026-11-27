<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminUserRequest;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Document;
use App\Models\LessonInteraction;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        $roleCounts = User::query()
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->select('roles.name', DB::raw('COUNT(users.id) as total'))
            ->groupBy('roles.name')
            ->pluck('total', 'name');

        return view('admin.dashboard', [
            'userCount' => (int) $roleCounts->sum(),
            'teacherCount' => (int) ($roleCounts['teacher'] ?? 0),
            'studentCount' => (int) ($roleCounts['student'] ?? 0),
            'courseCount' => Course::count(),
            'subjectCount' => Subject::count(),
            'pendingReviewCount' => Document::where('approval_status', Document::STATUS_PENDING)->count()
                + Assignment::where('status', Assignment::STATUS_PENDING)->count(),
            'openQuestionsCount' => LessonInteraction::whereNull('parent_id')
                ->where('type', LessonInteraction::TYPE_QUESTION)
                ->where('status', LessonInteraction::STATUS_OPEN)
                ->count(),
            'recentUsers' => User::with('role')->latest()->limit(5)->get(),
        ]);
    }

    public function users(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::with('role')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = trim((string) $request->input('q'));
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('username', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->whereRelation('role', 'name', $request->input('role')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    public function createUser(): View
    {
        $this->authorize('viewAny', User::class);

        return view('admin.users.create', ['roles' => Role::orderBy('id')->get()]);
    }

    public function storeUser(StoreAdminUserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return redirect()->route('admin.users.index')->with('status', 'Tài khoản đã được cấp thành công.');
    }

    public function toggleUserStatus(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['status' => 'Bạn không thể tự khóa tài khoản quản trị của chính mình.']);
        }

        $newStatus = $user->status === 'active' ? 'locked' : 'active';
        $user->update(['status' => $newStatus]);

        $msg = $newStatus === 'locked'
            ? "Đã khóa tài khoản \"{$user->name}\"."
            : "Đã mở khóa tài khoản \"{$user->name}\".";

        return back()->with('status', $msg);
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['role_id' => 'Bạn không thể tự hạ quyền tài khoản quản trị của chính mình.']);
        }

        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $user->update(['role_id' => (int) $validated['role_id']]);

        return back()->with('status', "Đã cập nhật vai trò cho tài khoản \"{$user->name}\".");
    }
}
