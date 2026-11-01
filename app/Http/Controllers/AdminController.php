<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminUserRequest;
use App\Models\Course;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'userCount' => User::count(),
            'teacherCount' => User::whereRelation('role', 'name', 'teacher')->count(),
            'studentCount' => User::whereRelation('role', 'name', 'student')->count(),
            'courseCount' => Course::count(),
            'subjectCount' => Subject::count(),
            'recentUsers' => User::with('role')->latest()->limit(5)->get(),
        ]);
    }

    public function users(): View
    {
        $this->authorize('viewAny', User::class);

        return view('admin.users.index', ['users' => User::with('role')->latest()->paginate(10)]);
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
}
