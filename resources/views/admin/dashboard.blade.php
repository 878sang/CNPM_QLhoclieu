@extends('layouts.admin')
@section('content')
    <x-page-header
        eyebrow="QUẢN TRỊ HỆ THỐNG"
        title="Bảng điều hành"
        description="Theo dõi tài khoản và học liệu trong một không gian rõ ràng."
    >
        <x-slot:actions>
            <x-button :href="route('admin.users.create')">
                <i class="bi bi-person-plus me-2" aria-hidden="true"></i>
                Cấp tài khoản
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        @foreach ([
            ['icon' => 'people', 'number' => '01', 'value' => $userCount, 'label' => 'Tổng tài khoản'],
            ['icon' => 'mortarboard', 'number' => '02', 'value' => $studentCount, 'label' => 'Học viên'],
            ['icon' => 'person-video3', 'number' => '03', 'value' => $teacherCount, 'label' => 'Giảng viên'],
            ['icon' => 'journal-text', 'number' => '04', 'value' => $courseCount, 'label' => 'Khóa học'],
        ] as $stat)
            <div class="col-sm-6 col-xl-3">
                <x-card class="p-3 h-100">
                    <div class="d-flex justify-content-between">
                        <span class="lm-icon"><i class="bi bi-{{ $stat['icon'] }}" aria-hidden="true"></i></span>
                        <span class="font-mono text-muted small">{{ $stat['number'] }}</span>
                    </div>
                    <div class="font-mono display-6 mt-3">{{ str_pad($stat['value'], 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="lm-text-muted">{{ $stat['label'] }}</div>
                </x-card>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <x-card class="p-4">
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                    <div>
                        <h2 class="h4 mb-1">Tài khoản mới nhất</h2>
                        <p class="lm-text-muted small mb-0">Các tài khoản vừa được cấp quyền.</p>
                    </div>
                    <a href="{{ route('admin.users.index') }}" class="small text-decoration-none">
                        Xem tất cả <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Người dùng</th>
                                <th>Vai trò</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentUsers as $user)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $user->name }}</div>
                                        <div class="small text-muted">{{ $user->email }}</div>
                                    </td>
                                    <td><x-badge tone="primary">{{ $user->role?->display_name }}</x-badge></td>
                                    <td>
                                        <x-badge :tone="$user->status === 'active' ? 'success' : 'danger'">
                                            {{ $user->status === 'active' ? 'Đang hoạt động' : 'Đã khóa' }}
                                        </x-badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="col-xl-4">
            <x-card class="p-4 h-100" style="border-top: 3px solid var(--accent)">
                <div class="font-mono text-accent small">QUẢN LÝ NHANH</div>
                <h2 class="h4 mt-3">Danh mục học liệu</h2>
                <p class="lm-text-muted small">Quản lý tài khoản và môn học trước khi mở khóa học.</p>
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary w-100 mt-3">Tạo tài khoản mới</a>
                <a href="{{ route('admin.subjects.create') }}" class="btn btn-outline-primary w-100 mt-2">Thêm môn học</a>
                <a href="{{ route('admin.subjects.index') }}" class="btn btn-outline-primary w-100 mt-2">Danh sách môn học</a>
            </x-card>
        </div>
    </div>
@endsection
