@extends('layouts.teacher')

@section('content')
<x-page-header eyebrow="GIẢNG VIÊN / HỌC LIỆU" title="Học liệu bài giảng" description="Theo dõi các học liệu đã thêm trong từng khóa học và trạng thái phê duyệt của Quản trị viên.">
    <x-slot:actions><x-button :href="route('teacher.dashboard')" variant="outline-secondary"><i class="bi bi-journal-text me-1"></i>Khóa học của tôi</x-button></x-slot:actions>
</x-page-header>

@if (session('status')) <x-alert tone="success" class="mb-4">{{ session('status') }}</x-alert> @endif

<x-card class="p-4 shadow-sm">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h2 class="h5 mb-1">Học liệu thuộc các khóa học của bạn ({{ $documents->total() }})</h2><p class="text-muted small mb-0">Muốn upload hoặc chỉnh sửa, hãy thực hiện trong mục “Khóa học của tôi”.</p></div>
        <form method="GET" action="{{ route('teacher.materials.index') }}" class="document-list-filter">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width: 180px;" placeholder="Tìm tên tài liệu...">
            <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Tất cả khóa học</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(request('course_id') == $course->id)>{{ $course->title }}</option>@endforeach</select>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Tất cả trạng thái</option><option value="pending" @selected(request('status') === 'pending')>Chờ duyệt</option><option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option><option value="rejected" @selected(request('status') === 'rejected')>Bị từ chối</option></select>
            <button type="submit" class="btn btn-sm btn-primary">Lọc</button>
        </form>
    </div>
    <div class="table-responsive"><table class="table align-middle mb-0 table-hover"><thead class="table-light"><tr><th>Học liệu</th><th>Khóa học / Bài học</th><th>Loại</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead><tbody>
    @forelse($documents as $doc)
        <tr><td><div class="d-flex align-items-center gap-2"><i class="bi {{ $doc->typeIcon() }} fs-4"></i><div><div class="fw-semibold">{{ $doc->title }}</div><div class="small text-muted">{{ $doc->original_name ?? $doc->url }}</div></div></div></td><td><div class="small fw-medium">{{ $doc->course->title }}</div><div class="small text-muted">{{ $doc->lesson?->title ?? 'Tài liệu chung' }}</div></td><td><span class="badge bg-light text-dark border">{{ $doc->typeLabel() }}</span></td><td><span class="badge {{ $doc->statusBadgeClass() }}">{{ $doc->statusLabel() }}</span>@if($doc->isRejected() && $doc->rejection_reason)<div class="small text-danger mt-1">{{ $doc->rejection_reason }}</div>@endif</td><td class="text-end"><div class="btn-group btn-group-sm">@if($doc->allow_view)<a href="{{ route('courses.documents.show', [$doc->course, $doc]) }}" target="_blank" class="btn btn-outline-primary" title="Xem"><i class="bi bi-eye"></i></a>@endif<button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editDocumentModal{{ $doc->id }}" title="Chỉnh sửa"><i class="bi bi-pencil"></i></button></div>@include('documents._edit-modal', ['document' => $doc, 'course' => $doc->course])</td></tr>
    @empty
        <tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-inbox display-6 d-block mb-2"></i>Chưa có học liệu nào.</td></tr>
    @endforelse
    </tbody></table></div>
    @if($documents->hasPages())<div class="mt-4">{{ $documents->links() }}</div>@endif
</x-card>
@endsection
