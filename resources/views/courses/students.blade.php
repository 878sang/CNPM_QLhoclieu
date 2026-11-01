@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')
@section('content')
<x-page-header eyebrow="KHÓA HỌC / HỌC VIÊN" :title="$course->title" description="Thêm hoặc xóa học viên của khóa học này.">
    <x-slot:actions>
        <button class="btn btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#addStudentsSidebar" aria-controls="addStudentsSidebar">
            <i class="bi bi-person-plus me-2" aria-hidden="true"></i>Thêm học viên
        </button>
        <x-button :href="route('courses.index')" variant="outline-primary">Quay lại danh sách</x-button>
    </x-slot:actions>
</x-page-header>
<div class="offcanvas offcanvas-end lm-add-students-sidebar" tabindex="-1" id="addStudentsSidebar" aria-labelledby="addStudentsSidebarLabel">
    <div class="offcanvas-header border-bottom">
        <div><div class="font-mono text-accent small text-uppercase">QUẢN LÝ LỚP</div><h2 class="h4 mb-0" id="addStudentsSidebarLabel">Thêm học viên</h2></div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng"></button>
    </div>
    <form method="POST" action="{{ route('courses.students.store', $course) }}" class="d-flex flex-column h-100">
        @csrf
        <div class="offcanvas-body">
            <p class="small text-muted">Tìm kiếm theo tên hoặc email, sau đó chọn một hoặc nhiều học viên.</p>
            <label for="student-search" class="visually-hidden">Tìm học viên</label>
            <div class="input-group mb-3"><span class="input-group-text bg-surface border-paper"><i class="bi bi-search" aria-hidden="true"></i></span><input id="student-search" class="form-control" type="search" placeholder="Tìm tên hoặc email..." data-student-search></div>
            <div class="d-flex justify-content-between align-items-center mb-2"><span class="small fw-semibold">Học viên khả dụng</span><span class="badge text-bg-secondary" data-selected-count>0 đã chọn</span></div>
            <div class="d-grid gap-2" data-student-list>
                @forelse($availableStudents as $student)
                    <label class="lm-student-option" data-student-option data-search="{{ strtolower($student->name . ' ' . $student->email) }}">
                        <input class="form-check-input mt-1" type="checkbox" name="user_id[]" value="{{ $student->id }}" @checked(in_array($student->id, old('user_id', []))) data-student-checkbox>
                        <span><span class="d-block fw-semibold">{{ $student->name }}</span><span class="d-block small text-muted">{{ $student->email }}</span></span>
                    </label>
                @empty
                    <div class="small text-muted py-3">Tất cả học viên đã có trong khóa học.</div>
                @endforelse
            </div>
            <div class="small text-muted mt-3" data-no-student-result hidden>Không tìm thấy học viên phù hợp.</div>
            @if($errors->has('user_id') || $errors->has('user_id.*'))<div class="invalid-feedback d-block">{{ $errors->first('user_id') ?: $errors->first('user_id.*') }}</div>@endif
        </div>
        <div class="offcanvas-footer border-top p-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit" data-add-students disabled>Thêm đã chọn</button><button class="btn btn-outline-primary" type="button" data-bs-dismiss="offcanvas">Hủy</button></div>
    </form>
</div>
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
