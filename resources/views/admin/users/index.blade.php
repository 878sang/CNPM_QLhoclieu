@extends('layouts.admin')
@section('content')
<x-page-header eyebrow="QUẢN TRỊ / TÀI KHOẢN" title="Danh sách tài khoản" description="Quản lý quyền truy cập của những người dùng trong hệ thống."><x-slot:actions><x-button :href="route('admin.users.create')"><i class="bi bi-person-plus me-2" aria-hidden="true"></i>Cấp tài khoản</x-button></x-slot:actions></x-page-header>
<x-card class="p-3 p-md-4">
    <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 mb-4">
        <div class="col-12 col-md-5">
            <label for="q" class="visually-hidden">Tìm tài khoản</label>
            <input type="search" name="q" id="q" value="{{ request('q') }}" class="form-control" placeholder="Tìm theo tên, username hoặc email">
        </div>
        <div class="col-12 col-md-3">
            <label for="role" class="visually-hidden">Vai trò</label>
            <select name="role" id="role" class="form-select">
                <option value="">Tất cả vai trò</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->display_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label for="status" class="visually-hidden">Trạng thái</label>
            <select name="status" id="status" class="form-select">
                <option value="">Tất cả trạng thái</option>
                <option value="active" @selected(request('status') === 'active')>Đang hoạt động</option>
                <option value="locked" @selected(request('status') === 'locked')>Đã khóa</option>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Lọc</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Xóa</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Họ và tên</th>
                    <th class="d-none d-md-table-cell">Username / Email</th>
                    <th>Vai trò</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $user->name }}</div>
                            <div class="small text-muted d-md-none">{{ $user->email }}</div>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <div>{{ $user->username }}</div>
                            <div class="small text-muted">{{ $user->email }}</div>
                        </td>
                        <td>
                            @if($user->id === auth()->id())
                                <x-badge tone="primary">{{ $user->role?->display_name }}</x-badge>
                            @else
                                <form method="POST" action="{{ route('admin.users.update-role', $user) }}" class="d-inline-flex align-items-center gap-1">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()" aria-label="Đổi vai trò">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->display_name }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @endif
                        </td>
                        <td>
                            <x-badge :tone="$user->status === 'active' ? 'success' : 'danger'">
                                {{ $user->status === 'active' ? 'Đang hoạt động' : 'Đã khóa' }}
                            </x-badge>
                        </td>
                        <td class="text-end">
                            @if($user->id === auth()->id())
                                <span class="small text-muted fst-italic">Tài khoản hiện tại</span>
                            @else
                                <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm {{ $user->status === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                        <i class="bi {{ $user->status === 'active' ? 'bi-lock-fill' : 'bi-unlock-fill' }} me-1"></i>{{ $user->status === 'active' ? 'Khóa TK' : 'Mở khóa' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state
                                title="Chưa có tài khoản"
                                message="Hãy cấp tài khoản đầu tiên cho hệ thống."
                                icon="people"
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links('pagination::bootstrap-5') }}</div>
</x-card>
@endsection
