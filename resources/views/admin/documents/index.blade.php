@extends('layouts.admin')

@section('content')
<div class="admin-review-page">
    <x-page-header eyebrow="QUẢN TRỊ / KIỂM DUYỆT" title="Kiểm duyệt nội dung" :description="($pendingCount + $pendingAssignmentsCount) . ' nội dung đang chờ duyệt. Chỉ nội dung đã duyệt mới hiển thị cho học viên.'" />

    @if (session('status'))
        <x-alert tone="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif
    @error('rejection_reason')<x-alert :message="$message" />@enderror

    <ul class="nav nav-tabs admin-review-tabs mb-4" id="reviewTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="content-tab" data-bs-toggle="tab" data-bs-target="#content-pane" type="button" role="tab" aria-controls="content-pane" aria-selected="true">
                <i class="bi bi-folder2-open me-2"></i>Nội dung
                <span class="badge ms-2">{{ $pendingCount }} chờ duyệt</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="assignments-tab" data-bs-toggle="tab" data-bs-target="#assignments-pane" type="button" role="tab" aria-controls="assignments-pane" aria-selected="false">
                <i class="bi bi-journal-check me-2"></i>Bài tập
                <span class="badge ms-2">{{ $pendingAssignmentsCount }} chờ duyệt</span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="reviewTabsContent">
        <div class="tab-pane fade show active" id="content-pane" role="tabpanel" aria-labelledby="content-tab" tabindex="0">
            <x-card class="admin-review-card p-3 p-md-4 mb-4">
                <div class="admin-review-card__heading">
                    <div><div class="admin-section-kicker">HỌC LIỆU</div><h2 class="h4 mb-1">Kiểm duyệt tài liệu</h2><p class="text-muted mb-0">Giáo viên gửi tài liệu, video và liên kết. Học viên chỉ xem được nội dung đã duyệt.</p></div>
                    <span class="admin-review-count">{{ $pendingCount }} chờ duyệt</span>
                </div>
                <form method="GET" action="{{ route('admin.documents.index') }}" class="admin-review-filters">
                    <div class="admin-review-filters__search"><label for="q" class="visually-hidden">Tìm nội dung</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="search" name="q" id="q" value="{{ request('q') }}" class="form-control" placeholder="Tìm theo tên tài liệu"></div></div>
                    <div class="admin-review-filters__status"><label for="status" class="visually-hidden">Trạng thái</label><select name="status" id="status" class="form-select"><option value="pending" @selected($status === 'pending')>Chờ duyệt</option><option value="approved" @selected($status === 'approved')>Đã duyệt</option><option value="rejected" @selected($status === 'rejected')>Bị từ chối</option><option value="all" @selected($status === 'all')>Tất cả trạng thái</option></select></div>
                    <button type="submit" class="btn btn-primary">Lọc</button><a href="{{ route('admin.documents.index') }}" class="btn btn-outline-secondary">Xóa lọc</a>
                </form>
                <div class="table-responsive admin-review-table-wrap"><table class="table admin-review-table align-middle mb-0"><thead><tr><th>Nội dung</th><th>Giáo viên</th><th class="d-none d-lg-table-cell">Khóa học</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead><tbody>
                @forelse($documents as $document)
                    <tr><td><div class="fw-semibold">{{ $document->title }}</div><div class="small text-muted">{{ $document->typeLabel() }} · {{ $document->created_at->format('d/m/Y H:i') }}</div></td><td>{{ $document->course->teacher?->name ?? '—' }}</td><td class="d-none d-lg-table-cell">{{ $document->course->title }}</td><td><span class="badge {{ $document->statusBadgeClass() }}">{{ $document->statusLabel() }}</span>@if($document->approval_status === 'rejected')<div class="small text-muted mt-1">{{ $document->rejection_reason }}</div>@endif</td><td class="text-end"><div class="admin-review-actions"><a href="{{ route('courses.documents.show', [$document->course, $document]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Xem</a>@unless($document->isApproved())<form method="POST" action="{{ route('admin.documents.approve', $document) }}">@csrf<button class="btn btn-sm btn-success">Duyệt</button></form>@endunless @if($document->approval_status !== 'rejected')<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reject-document-{{ $document->id }}">Từ chối</button>@endif</div></td></tr>
                @empty
                    <tr><td colspan="5"><x-empty-state title="Không có tài liệu" message="Không có tài liệu nào khớp với bộ lọc hiện tại." icon="patch-check" /></td></tr>
                @endforelse
                </tbody></table></div>{{ $documents->links('pagination::bootstrap-5') }}
            </x-card>

            <x-card class="admin-review-card p-3 p-md-4">
                <div class="admin-review-card__heading"><div><div class="admin-section-kicker">BÀI GIẢNG</div><h2 class="h4 mb-1">Duyệt bài giảng</h2><p class="text-muted mb-0">Gồm thay đổi tên bài, video và tài liệu đính kèm.</p></div><span class="admin-review-count">{{ $pendingLessons->count() }} chờ duyệt</span></div>
                @forelse($pendingLessons as $lesson)
                    <div class="admin-lesson-row"><div><div class="fw-semibold">{{ $lesson->title }}</div><div class="small text-muted">{{ $lesson->chapter?->course?->title }} · {{ $lesson->documents->count() }} học liệu chờ duyệt</div></div><div class="admin-review-actions">@if($lesson->isPending())<form method="POST" action="{{ route('admin.lessons.approve', $lesson) }}">@csrf<button class="btn btn-sm btn-success">Duyệt bài</button></form><form method="POST" action="{{ route('admin.lessons.reject', $lesson) }}">@csrf<button class="btn btn-sm btn-outline-danger">Từ chối</button></form>@else<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Có học liệu mới</span>@endif</div></div>
                @empty
                    <x-empty-state title="Không có bài giảng chờ duyệt" message="Nội dung mới từ giáo viên sẽ xuất hiện tại đây." icon="journal-check" />
                @endforelse
            </x-card>
        </div>

        <div class="tab-pane fade" id="assignments-pane" role="tabpanel" aria-labelledby="assignments-tab" tabindex="0">
            <x-card class="admin-review-card p-3 p-md-4">
                <div class="admin-review-card__heading"><div><div class="admin-section-kicker">BÀI TẬP</div><h2 class="h4 mb-1">Kiểm duyệt bài tập</h2><p class="text-muted mb-0">Bài tập giáo viên gửi duyệt sẽ chưa mở cho học viên cho đến khi Admin phê duyệt.</p></div><span class="admin-review-count">{{ $pendingAssignmentsCount }} chờ duyệt</span></div>
                <div class="table-responsive admin-review-table-wrap"><table class="table admin-review-table align-middle mb-0"><thead><tr><th>Bài tập</th><th>Giáo viên</th><th class="d-none d-md-table-cell">Khóa học</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead><tbody>
                @forelse($assignments as $assignment)
                    <tr><td><div class="fw-semibold">{{ $assignment->title }}</div><div class="small text-muted">{{ $assignment->lesson?->title ?? 'Bài tập chung của khóa' }} · {{ $assignment->created_at->format('d/m/Y H:i') }}</div></td><td>{{ $assignment->course->teacher?->name ?? '—' }}</td><td class="d-none d-md-table-cell">{{ $assignment->course->title }}</td><td><span class="badge {{ $assignment->statusBadgeClass() }}">{{ $assignment->statusLabel() }}</span>@if($assignment->rejection_reason)<div class="small text-muted mt-1">{{ $assignment->rejection_reason }}</div>@endif</td><td class="text-end"><div class="admin-review-actions"><a href="{{ route('courses.assignments.show', [$assignment->course, $assignment]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Xem</a>@unless($assignment->isPublished())<form method="POST" action="{{ route('admin.assignments.approve', $assignment) }}">@csrf<button class="btn btn-sm btn-success">Duyệt</button></form>@endunless<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reject-assignment-{{ $assignment->id }}">Từ chối</button></div></td></tr>
                @empty
                    <tr><td colspan="5"><x-empty-state title="Không có bài tập" message="Không có bài tập nào khớp với bộ lọc hiện tại." icon="patch-check" /></td></tr>
                @endforelse
                </tbody></table></div>{{ $assignments->links('pagination::bootstrap-5') }}
            </x-card>
        </div>
    </div>
</div>

@foreach($documents as $document)
    <div class="modal fade" id="reject-document-{{ $document->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('admin.documents.reject', $document) }}" class="modal-content">@csrf<div class="modal-header"><h2 class="modal-title h5">Từ chối tài liệu</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><p class="text-muted">{{ $document->title }}</p><label class="form-label" for="document-reason-{{ $document->id }}">Lý do từ chối</label><textarea name="rejection_reason" id="document-reason-{{ $document->id }}" rows="4" maxlength="1000" required class="form-control"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-danger">Xác nhận từ chối</button></div></form></div></div>
@endforeach
@foreach($assignments as $assignment)
    <div class="modal fade" id="reject-assignment-{{ $assignment->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('admin.assignments.reject', $assignment) }}" class="modal-content">@csrf<div class="modal-header"><h2 class="modal-title h5">Từ chối bài tập</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><p class="text-muted">{{ $assignment->title }}</p><label class="form-label" for="assignment-reason-{{ $assignment->id }}">Lý do từ chối</label><textarea name="rejection_reason" id="assignment-reason-{{ $assignment->id }}" rows="4" maxlength="1000" required class="form-control"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-danger">Xác nhận từ chối</button></div></form></div></div>
@endforeach
@endsection
