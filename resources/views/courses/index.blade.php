@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')
@section('content')
@php($statusLabels = ['draft' => ['Nháp', 'secondary'], 'public' => ['Công khai', 'success'], 'archived' => ['Lưu trữ', 'dark']])
<x-page-header eyebrow="KHÓA HỌC" title="Danh sách khóa học" description="Tạo, chỉnh sửa khóa học và quản lý học viên tham gia.">
    <x-slot:actions><x-button :href="route('courses.create')"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Tạo khóa học</x-button></x-slot:actions>
</x-page-header>
<x-card class="p-3 p-md-4">
    <form method="GET" action="{{ route('courses.index') }}" class="row g-2 mb-4">
        <div class="col-12 col-md-4">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tìm theo tên hoặc mô tả">
        </div>
        <div class="col-12 col-md-3">
            <select name="subject_id" class="form-select">
                <option value="">Tất cả môn học</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <select name="status" class="form-select">
                <option value="">Tất cả trạng thái</option>
                <option value="draft" @selected(request('status') === 'draft')>Nháp</option>
                <option value="public" @selected(request('status') === 'public')>Công khai</option>
                <option value="archived" @selected(request('status') === 'archived')>Lưu trữ</option>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Lọc</button>
            <a href="{{ route('courses.index') }}" class="btn btn-outline-secondary">Xóa</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Khóa học</th><th class="d-none d-md-table-cell">Giảng viên</th><th>Trạng thái</th><th>Học viên</th><th class="text-end">Thao tác</th></tr></thead>
            <tbody>
                @forelse($courses as $course)
                    <tr>
                        <td><a href="{{ route('courses.show', $course) }}" class="text-decoration-none"><div class="fw-semibold">{{ $course->title }}</div><div class="small text-muted">{{ $course->subject->name }}</div></a></td>
                        <td class="d-none d-md-table-cell">{{ $course->teacher->name }}</td>
                        <td><x-badge :tone="$statusLabels[$course->status][1]">{{ $statusLabels[$course->status][0] }}</x-badge></td>
                        <td>{{ $course->enrollments_count }}</td>
                        <td class="text-end text-nowrap">
                            <x-button :href="route('courses.students.index', $course)" variant="outline-primary" class="btn-sm">Học viên</x-button>
                            <x-button :href="route('courses.documents.index', $course)" variant="outline-primary" class="btn-sm">Tài liệu</x-button>
                            <x-button :href="route('courses.edit', $course)" variant="outline-secondary" class="btn-sm">Sửa</x-button>
                            <form method="POST" action="{{ route('courses.destroy', $course) }}" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa khóa học này?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state title="Chưa có khóa học" message="Hãy tạo khóa học đầu tiên." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $courses->links('pagination::bootstrap-5') }}</div>
</x-card>
@endsection
