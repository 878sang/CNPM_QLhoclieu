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
                        <td><x-badge tone="primary">{{ $user->role?->display_name }}</x-badge></td>
                        <td>
                            <x-badge :tone="$user->status === 'active' ? 'success' : 'danger'">
                                {{ $user->status === 'active' ? 'Đang hoạt động' : 'Đã khóa' }}
                            </x-badge>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
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

    <div class="mt-4">{{ $users->links() }}</div>
</x-card>
@endsection
