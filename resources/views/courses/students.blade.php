@extends('layouts.app')
@section('content')
<x-page-header eyebrow="KHÓA HỌC / HỌC VIÊN" :title="$course->title" description="Thêm hoặc xóa học viên của khóa học này.">
    <x-slot:actions><x-button :href="route('courses.index')" variant="outline-primary">Quay lại danh sách</x-button></x-slot:actions>
</x-page-header>
<x-card class="p-3 p-md-4 mb-4">
    <form method="POST" action="{{ route('courses.students.store', $course) }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md">
            <label for="user_id" class="form-label">Thêm học viên</label>
            <select name="user_id" id="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                <option value="">Chọn học viên</option>
                @foreach($availableStudents as $student)
                    <option value="{{ $student->id }}" @selected(old('user_id') == $student->id)>{{ $student->name }} ({{ $student->email }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-auto"><x-button><i class="bi bi-person-plus me-2" aria-hidden="true"></i>Thêm</x-button></div>
    </form>
</x-card>
<x-card class="p-3 p-md-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Họ và tên</th><th class="d-none d-md-table-cell">Email</th><th>Ngày thêm</th><th class="text-end">Thao tác</th></tr></thead>
            <tbody>
                @forelse($enrollments as $enrollment)
                    <tr>
                        <td><div class="fw-semibold">{{ $enrollment->user->name }}</div><div class="small text-muted d-md-none">{{ $enrollment->user->email }}</div></td>
                        <td class="d-none d-md-table-cell">{{ $enrollment->user->email }}</td>
                        <td>{{ $enrollment->created_at->format('d/m/Y') }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('courses.students.destroy', [$course, $enrollment]) }}" onsubmit="return confirm('Xóa học viên này khỏi khóa học?')">
                                @csrf
                                @method('DELETE')
                                <x-button variant="outline-danger" class="btn-sm">Xóa</x-button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state title="Chưa có học viên" message="Hãy thêm học viên đầu tiên vào khóa học." icon="people" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $enrollments->links('pagination::bootstrap-5') }}</div>
</x-card>
@endsection
