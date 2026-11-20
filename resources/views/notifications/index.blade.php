@php
    $layout = auth()->user()->hasRole('admin')
        ? 'layouts.admin'
        : (auth()->user()->hasRole('teacher') ? 'layouts.teacher' : 'layouts.student');
@endphp
@extends($layout)

@section('content')
<x-page-header
    eyebrow="THÔNG BÁO"
    title="Quản lý thông báo"
    description="Xem thông báo từ hệ thống, khóa học, tương tác hỏi đáp và gửi thông báo tới học viên."
>
    <x-slot:actions>
        <div class="d-flex flex-wrap gap-2">
            @if($unreadCount > 0 && $tab === 'inbox')
                <form method="POST" action="{{ route('notifications.mark_all_read') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-check2-all me-1"></i>Đánh dấu tất cả đã đọc ({{ $unreadCount }})
                    </button>
                </form>
            @endif
            @if($canSend)
                <a href="{{ route('notifications.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-megaphone me-1"></i>Gửi thông báo mới
                </a>
            @endif
        </div>
    </x-slot:actions>
</x-page-header>

@if (session('status'))
    <x-alert type="success" class="mb-3">{{ session('status') }}</x-alert>
@endif

@if($canSend)
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link fw-semibold {{ $tab === 'inbox' ? 'active' : '' }}" href="{{ route('notifications.index', ['tab' => 'inbox']) }}">
            <i class="bi bi-inbox me-1"></i>Hộp thư đến
            @if($unreadCount > 0)
                <span class="badge bg-danger ms-1">{{ $unreadCount }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-semibold {{ $tab === 'sent' ? 'active' : '' }}" href="{{ route('notifications.index', ['tab' => 'sent']) }}">
            <i class="bi bi-send me-1"></i>Thông báo đã gửi
        </a>
    </li>
</ul>
@endif

<!-- BỘ LỌC -->
<x-card class="p-3 mb-4">
    <form method="GET" action="{{ route('notifications.index') }}" class="row g-2 align-items-center">
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if($tab === 'inbox')
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Tất cả ({{ $totalCount }}) --</option>
                    <option value="unread" @selected(request('status') === 'unread')>Chưa đọc ({{ $unreadCount }})</option>
                    <option value="read" @selected(request('status') === 'read')>Đã đọc</option>
                </select>
            </div>
        @endif
        <div class="col-md-3">
            <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Tất cả danh mục --</option>
                @foreach(\App\Models\AppNotification::TYPES as $key => $label)
                    <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-5">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Tìm theo tiêu đề hoặc nội dung thông báo...">
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Lọc</button>
            @if(request()->hasAny(['status', 'type', 'q']))
                <a href="{{ route('notifications.index', ['tab' => $tab]) }}" class="btn btn-sm btn-outline-secondary">Xóa</a>
            @endif
        </div>
    </form>
</x-card>

@if($tab === 'inbox')
    <x-card class="p-0 overflow-hidden">
        <div class="list-group list-group-flush">
            @forelse($notifications as $item)
                <div class="list-group-item p-3 p-md-4 {{ $item->isRead() ? 'bg-white' : 'bg-primary-subtle bg-opacity-25' }}">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="fs-4 mt-1">
                                <i class="bi {{ $item->typeIcon() }}"></i>
                            </div>
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    @if(! $item->isRead())
                                        <span class="badge bg-primary">Mới</span>
                                    @endif
                                    <span class="badge {{ $item->typeBadgeClass() }}">{{ $item->typeLabel() }}</span>
                                    @if($item->course)
                                        <span class="badge bg-light text-dark border">{{ $item->course->title }}</span>
                                    @endif
                                    <span class="small text-muted">{{ $item->created_at->diffForHumans() }}</span>
                                </div>
                                <h3 class="h6 fw-bold mb-1 text-dark">{{ $item->title }}</h3>
                                <p class="small text-secondary mb-1" style="white-space: pre-line;">{{ $item->message }}</p>
                                @if($item->sender)
                                    <div class="small text-muted">Người gửi: {{ $item->sender->name }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="d-flex flex-shrink-0 gap-1">
                            @if($item->action_url)
                                <a href="{{ route('notifications.read', $item) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Xem
                                </a>
                            @elseif(! $item->isRead())
                                <a href="{{ route('notifications.read', $item) }}" class="btn btn-sm btn-outline-success" title="Đánh dấu đã đọc">
                                    <i class="bi bi-check2"></i>
                                </a>
                            @endif
                            <form method="POST" action="{{ route('notifications.destroy', $item) }}" onsubmit="return confirm('Xóa thông báo này?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa thông báo">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-4">
                    <x-empty-state
                        icon="bell-slash"
                        title="Không có thông báo nào"
                        message="Bạn đã cập nhật đầy đủ. Thông báo mới từ hệ thống và khóa học sẽ hiển thị tại đây."
                    />
                </div>
            @endforelse
        </div>
    </x-card>
    <div class="mt-3">{{ $notifications->links('pagination::bootstrap-5') }}</div>
@else
    <!-- TAB ĐÃ GỬI -->
    <x-card class="p-3 p-md-4">
        <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tiêu đề & Nội dung</th>
                        <th>Người nhận</th>
                        <th>Danh mục</th>
                        <th>Trạng thái</th>
                        <th>Thời gian</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sentNotifications as $sent)
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark">{{ $sent->title }}</div>
                                <div class="small text-muted">{{ Str::limit($sent->message, 90) }}</div>
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $sent->user?->name }}</div>
                                <div class="small text-muted">{{ $sent->course?->title ?? $sent->user?->email }}</div>
                            </td>
                            <td><span class="badge {{ $sent->typeBadgeClass() }}">{{ $sent->typeLabel() }}</span></td>
                            <td>
                                @if($sent->isRead())
                                    <span class="badge bg-success-subtle text-success border border-success">Đã đọc</span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning">Chưa đọc</span>
                                @endif
                            </td>
                            <td class="small text-muted text-nowrap">{{ $sent->created_at->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('notifications.destroy', $sent) }}" onsubmit="return confirm('Xóa thông báo đã gửi này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state
                                    icon="send"
                                    title="Chưa gửi thông báo nào"
                                    message="Bấm nút 'Gửi thông báo mới' ở góc trên để gửi thông báo đến học viên hoặc khóa học."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $sentNotifications->links('pagination::bootstrap-5') }}</div>
    </x-card>
@endif
@endsection
