@extends('layouts.admin')

@section('content')
<x-page-header eyebrow="QUẢN TRỊ / BÀI GIẢNG" title="Duyệt bài giảng" description="Giáo viên gửi bài học và học liệu từ khóa học của họ. Chỉ nội dung đã được duyệt mới hiển thị cho học viên." />
@if (session('status')) <x-alert tone="success" class="mb-4">{{ session('status') }}</x-alert> @endif

<x-card class="p-4 shadow-sm mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1">Bài học cần duyệt ({{ $pendingLessons->count() }})</h2><p class="text-muted small mb-0">Gồm thay đổi tên bài, video và tài liệu đính kèm.</p></div></div>
    @forelse($pendingLessons as $lesson)
        @php($pendingDocuments = $lesson->documents)
        <div class="border rounded mb-2 overflow-hidden">
            <div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-3 bg-light">
                <button type="button" class="btn btn-link text-start text-decoration-none text-dark p-0 flex-grow-1" data-bs-toggle="collapse" data-bs-target="#pendingLesson{{ $lesson->id }}" aria-expanded="false">
                    <span class="fw-semibold d-block"><i class="bi bi-chevron-right me-2 pending-chevron"></i><i class="bi bi-journal-bookmark text-primary me-1"></i>{{ $lesson->title }}</span>
                    <span class="small text-muted ms-4">{{ $lesson->chapter?->course?->title }} · {{ $pendingDocuments->count() }} học liệu chờ duyệt</span>
                </button>
                @if($lesson->isPending())
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('admin.lessons.approve', $lesson) }}">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Duyệt bài</button></form>
                        <form method="POST" action="{{ route('admin.lessons.reject', $lesson) }}">@csrf<button class="btn btn-sm btn-outline-danger">Từ chối</button></form>
                    </div>
                @else
                    <span class="badge bg-warning text-dark">Có học liệu mới chờ duyệt</span>
                @endif
            </div>
            <div class="collapse border-top" id="pendingLesson{{ $lesson->id }}">
                <div class="p-3"><div class="small text-muted mb-2">Nội dung thay đổi đang chờ duyệt:</div>
                    @forelse($pendingDocuments as $doc)
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border rounded p-2 mb-2"><div><i class="bi {{ $doc->typeIcon() }} me-2"></i><span class="fw-semibold">{{ $doc->title }}</span><span class="badge bg-light text-dark border ms-2">{{ $doc->typeLabel() }}</span>@if($doc->original_name)<div class="small text-muted ms-4">{{ $doc->original_name }}</div>@elseif($doc->url)<div class="small text-muted ms-4 text-truncate">{{ $doc->url }}</div>@endif</div><div class="d-flex gap-1">@if($doc->file_path || $doc->url)<a href="{{ route('courses.documents.show', [$lesson->chapter->course, $doc]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Xem trước</a>@endif<form method="POST" action="{{ route('admin.documents.approve', $doc) }}">@csrf<button class="btn btn-sm btn-outline-success">Duyệt</button></form><form method="POST" action="{{ route('admin.documents.reject', $doc) }}">@csrf<button class="btn btn-sm btn-outline-danger">Từ chối</button></form></div></div>
                    @empty
                        <div class="small text-muted">Thay đổi tên bài học đang chờ duyệt.</div>
                    @endforelse
                </div>
            </div>
        </div>
    @empty <x-empty-state title="Không có bài học chờ duyệt" message="Nội dung mới từ giáo viên sẽ xuất hiện tại đây." /> @endforelse
</x-card>

@endsection
