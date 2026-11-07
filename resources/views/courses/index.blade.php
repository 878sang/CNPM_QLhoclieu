@extends('layouts.app')
@section('content')
@php($statusLabels = ['draft' => ['Nháp', 'secondary'], 'public' => ['Công khai', 'success'], 'archived' => ['Lưu trữ', 'dark']])
<x-page-header eyebrow="KHÓA HỌC" title="Danh sách khóa học" description="Tạo, chỉnh sửa khóa học và quản lý học viên tham gia.">
    <x-slot:actions><x-button :href="route('courses.create')"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Tạo khóa học</x-button></x-slot:actions>
</x-page-header>
<x-card class="p-3 p-md-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Khóa học</th><th class="d-none d-md-table-cell">Giảng viên</th><th>Trạng thái</th><th>Học viên</th><th class="text-end">Thao tác</th></tr></thead>
            <tbody>
                @forelse($courses as $course)
                    <tr>
                        <td><div class="fw-semibold">{{ $course->title }}</div><div class="small text-muted">{{ $course->subject->name }}</div></td>
                        <td class="d-none d-md-table-cell">{{ $course->teacher->name }}</td>
                        <td><x-badge :tone="$statusLabels[$course->status][1]">{{ $statusLabels[$course->status][0] }}</x-badge></td>
                        <td>{{ $course->enrollments_count }}</td>
                        <td class="text-end text-nowrap">
                            <x-button :href="route('courses.students.index', $course)" variant="outline-primary" class="btn-sm">Học viên</x-button>
                            <x-button :href="route('courses.documents.index', $course)" variant="outline-primary" class="btn-sm">Tài liệu</x-button>
                            <x-button :href="route('courses.edit', $course)" variant="outline-secondary" class="btn-sm">Sửa</x-button>
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
