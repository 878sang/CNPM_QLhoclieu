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

    <div class="d-flex flex-column gap-3">
        @forelse($courses as $course)
            <div class="card border rounded-3 overflow-hidden shadow-sm hover-shadow transition-all bg-white">
                <div class="row g-0 align-items-center">
                    <!-- Ảnh bìa ô ngang (16:9) -->
                    <div class="col-md-4 col-lg-3 position-relative" style="min-height: 170px;">
                        <a href="{{ route('courses.show', $course) }}" class="d-block h-100 w-100">
                            <img src="{{ $course->cover_url }}" alt="{{ $course->title }}" class="w-100 h-100 object-fit-cover" style="min-height: 170px; max-height: 200px;">
                        </a>
                        <div class="position-absolute top-0 start-0 m-2">
                            <x-badge tone="primary">{{ $course->subject?->name }}</x-badge>
                        </div>
                        <div class="position-absolute top-0 end-0 m-2">
                            <x-badge :tone="$statusLabels[$course->status][1]">{{ $statusLabels[$course->status][0] }}</x-badge>
                        </div>
                    </div>

                    <!-- Thông tin chi tiết khóa học -->
                    <div class="col-md-5 col-lg-6 p-3 p-lg-4">
                        <div class="d-flex align-items-center gap-2 mb-1 text-muted small">
                            <span><i class="bi bi-person-circle me-1"></i>{{ $course->teacher?->name }}</span>
                            <span>•</span>
                            <span><i class="bi bi-calendar-event me-1"></i>{{ $course->start_at?->format('d/m/Y') ?: 'Bắt đầu ngay' }} - {{ $course->end_at?->format('d/m/Y') ?: 'Vô thời hạn' }}</span>
                        </div>

                        <h2 class="h5 mb-2 fw-bold">
                            <a href="{{ route('courses.show', $course) }}" class="text-dark text-decoration-none hover-primary">
                                {{ $course->title }}
                            </a>
                        </h2>

                        <p class="small text-muted mb-3 text-truncate-2">
                            {{ Str::limit($course->description, 130) ?: 'Khóa học chưa có phần mô tả nội dung.' }}
                        </p>

                        <div class="d-flex flex-wrap align-items-center gap-3 small text-secondary">
                            <span><i class="bi bi-people-fill text-primary me-1"></i><strong>{{ $course->enrollments_count }}</strong> học viên</span>
                            <span><i class="bi bi-folder-fill text-warning me-1"></i><strong>{{ $course->chapters()->count() }}</strong> chương</span>
                            <span><i class="bi bi-journal-text text-info me-1"></i><strong>{{ $course->documents()->count() }}</strong> học liệu</span>
                        </div>
                    </div>

                    <!-- Cột thao tác điều hướng -->
                    <div class="col-md-3 col-lg-3 p-3 p-lg-4 border-start-md bg-light d-flex flex-column gap-2 justify-content-center h-100" style="min-height: 170px;">
                        <a href="{{ route('courses.show', $course) }}" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-layout-text-window-reverse me-1"></i>Nội dung & Học liệu
                        </a>
                        <a href="{{ route('courses.students.index', $course) }}" class="btn btn-outline-info btn-sm w-100">
                            <i class="bi bi-people me-1"></i>Quản lý học viên ({{ $course->enrollments_count }})
                        </a>
                        <div class="d-flex gap-2">
                            <a href="{{ route('courses.edit', $course) }}" class="btn btn-outline-secondary btn-sm flex-grow-1" title="Chỉnh sửa khóa học">
                                <i class="bi bi-pencil me-1"></i>Sửa
                            </a>
                            <form method="POST" action="{{ route('courses.destroy', $course) }}" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa khóa học này và toàn bộ dữ liệu liên quan?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Xóa khóa học">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <x-empty-state title="Chưa có khóa học nào" message="Hãy tạo khóa học đầu tiên để bắt đầu đăng tải giáo trình và học liệu." />
        @endforelse
    </div>
    <div class="mt-4">{{ $courses->links('pagination::bootstrap-5') }}</div>
</x-card>
@endsection
