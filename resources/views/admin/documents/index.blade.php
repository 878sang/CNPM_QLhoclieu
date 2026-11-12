@extends('layouts.admin')
@section('content')
<x-page-header eyebrow="QUẢN TRỊ / BÀI TẬP" title="Kiểm duyệt bài tập" :description="$pendingCount.' bài tập đang chờ duyệt. Chỉ bài tập đã duyệt mới hiển thị cho học viên.'" />
@error('rejection_reason')<x-alert :message="$message" />@enderror
<x-card class="p-3 p-md-4">
    <form method="GET" action="{{ route('admin.documents.index') }}" class="row g-2 mb-4">
        <div class="col-12 col-md-6">
            <label for="q" class="visually-hidden">Tìm bài tập</label>
            <input type="search" name="q" id="q" value="{{ request('q') }}" class="form-control" placeholder="Tìm theo tên bài tập">
        </div>
        <div class="col-12 col-md-3">
            <label for="status" class="visually-hidden">Trạng thái</label>
            <select name="status" id="status" class="form-select">
                <option value="pending" @selected($status === 'pending')>Chờ duyệt</option>
                <option value="approved" @selected($status === 'approved')>Đã duyệt</option>
                <option value="rejected" @selected($status === 'rejected')>Bị từ chối</option>
                <option value="all" @selected($status === 'all')>Tất cả trạng thái</option>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Lọc</button>
            <a href="{{ route('admin.documents.index') }}" class="btn btn-outline-secondary">Xóa</a>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Bài tập</th><th>Giáo viên</th><th class="d-none d-md-table-cell">Khóa học</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
            <tbody>
                @forelse($documents as $document)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $document->title }}</div>
                            <div class="small text-muted">{{ $document->original_name }} · {{ number_format($document->size / 1024, 0, ',', '.') }} KB · {{ $document->created_at->format('d/m/Y H:i') }}</div>
                        </td>
                        <td>{{ $document->course->teacher?->name ?? '—' }}</td>
                        <td class="d-none d-md-table-cell">{{ $document->course->title }}</td>
                        <td>
                            @include('documents._status-badge', ['document' => $document])
                            @if ($document->approval_status === 'rejected')
                                <div class="small text-muted mt-1">Lý do: {{ $document->rejection_reason }}</div>
                            @endif
                            @if ($document->reviewed_at)
                                <div class="small text-muted mt-1">{{ $document->reviewer?->name }} · {{ $document->reviewed_at->format('d/m/Y H:i') }}</div>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <x-button :href="route('courses.documents.show', [$document->course, $document])" variant="outline-primary" class="btn-sm" target="_blank">Xem</x-button>
                            @unless ($document->isApproved())
                                <form method="POST" action="{{ route('admin.documents.approve', $document) }}" class="d-inline">
                                    @csrf
                                    <x-button variant="success" class="btn-sm">Duyệt</x-button>
                                </form>
                            @endunless
                            <x-button :href="route('courses.documents.edit', [$document->course, $document])" variant="outline-secondary" class="btn-sm">Sửa</x-button>
                            <form method="POST" action="{{ route('courses.documents.destroy', [$document->course, $document]) }}" class="d-inline" onsubmit="return confirm('Xóa bài tập này?')">
                                @csrf
                                @method('DELETE')
                                <x-button variant="outline-danger" class="btn-sm">Xóa</x-button>
                            </form>
                            @if ($document->approval_status !== 'rejected')
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#reject-{{ $document->id }}">Từ chối</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state title="Không có bài tập" message="Không có bài tập nào khớp với bộ lọc hiện tại." icon="patch-check" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $documents->links('pagination::bootstrap-5') }}</div>
</x-card>

@foreach($documents as $document)
    <div class="modal fade" id="reject-{{ $document->id }}" tabindex="-1" aria-labelledby="reject-title-{{ $document->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('admin.documents.reject', $document) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h5" id="reject-title-{{ $document->id }}">Từ chối bài tập</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">{{ $document->title }} — {{ $document->course->title }}</p>
                    <label for="reason-{{ $document->id }}" class="form-label">Lý do từ chối <span class="text-accent" aria-hidden="true">*</span></label>
                    <textarea name="rejection_reason" id="reason-{{ $document->id }}" rows="4" maxlength="1000" required class="form-control"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">Xác nhận từ chối</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
