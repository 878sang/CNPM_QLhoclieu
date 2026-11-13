@extends('layouts.teacher')
@section('content')
<x-page-header eyebrow="HỌC LIỆU / BÀI TẬP" title="Quản lý bài tập" description="Tất cả bài tập thuộc các khóa học bạn phụ trách. Chỉ bài đã được Admin duyệt mới hiển thị cho học viên." />
<div class="row g-3 mb-4">
    @foreach(['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Bị từ chối'] as $key => $label)
        <div class="col-12 col-md-4">
            <a href="{{ route('teacher.materials', ['status' => $key]) }}" class="text-decoration-none">
                <x-card class="p-3"><div class="small text-muted">{{ $label }}</div><div class="h3 mb-0">{{ $counts[$key] ?? 0 }}</div></x-card>
            </a>
        </div>
    @endforeach
</div>
<x-card class="p-3 p-md-4">
    <form method="GET" action="{{ route('teacher.materials') }}" class="row g-2 mb-4">
        <div class="col-12 col-md-4">
            <label for="q" class="visually-hidden">Tìm bài tập</label>
            <input type="search" name="q" id="q" value="{{ request('q') }}" class="form-control" placeholder="Tìm theo tên bài tập">
        </div>
        <div class="col-12 col-md-3">
            <label for="course_id" class="visually-hidden">Khóa học</label>
            <select name="course_id" id="course_id" class="form-select">
                <option value="">Tất cả khóa học</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}" @selected(request('course_id') == $course->id)>{{ $course->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="status" class="visually-hidden">Trạng thái</label>
            <select name="status" id="status" class="form-select">
                <option value="">Tất cả trạng thái</option>
                <option value="pending" @selected(request('status') === 'pending')>Chờ duyệt</option>
                <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                <option value="rejected" @selected(request('status') === 'rejected')>Bị từ chối</option>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Lọc</button>
            <a href="{{ route('teacher.materials') }}" class="btn btn-outline-secondary">Xóa</a>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Bài tập</th><th class="d-none d-md-table-cell">Khóa học</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
            <tbody>
                @forelse($documents as $document)
                    <tr>
                        <td><div class="fw-semibold">{{ $document->title }}</div><div class="small text-muted">{{ $document->original_name }} · {{ number_format($document->size / 1024, 0, ',', '.') }} KB · {{ $document->created_at->format('d/m/Y') }}</div></td>
                        <td class="d-none d-md-table-cell">{{ $document->course->title }}</td>
                        <td>
                            @include('documents._status-badge', ['document' => $document])
                            @if ($document->approval_status === 'rejected')
                                <div class="small text-danger mt-1">Lý do: {{ $document->rejection_reason }}</div>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <x-button :href="route('courses.documents.show', [$document->course, $document])" variant="outline-primary" class="btn-sm" target="_blank">Xem</x-button>
                            <x-button :href="route('courses.documents.edit', [$document->course, $document])" variant="outline-secondary" class="btn-sm">Sửa</x-button>
                            <form method="POST" action="{{ route('courses.documents.destroy', [$document->course, $document]) }}" class="d-inline" onsubmit="return confirm('Xóa bài tập này?')">
                                @csrf
                                @method('DELETE')
                                <x-button variant="outline-danger" class="btn-sm">Xóa</x-button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state title="Chưa có bài tập" message="Không có bài tập nào khớp với bộ lọc. Tải bài tập mới từ trang tài liệu của từng khóa học." icon="file-earmark" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $documents->links('pagination::bootstrap-5') }}</div>
</x-card>
@endsection
