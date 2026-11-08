@extends('layouts.student')

@section('content')
    <x-page-header
        eyebrow="THƯ VIỆN HỌC LIỆU"
        title="Tìm học liệu"
        description="Khám phá các khóa ôn thi THPT đang được mở."
    />

    <form method="GET" action="{{ route('student.search') }}" class="mb-4">
        <div class="input-group input-group-lg">
            <span class="input-group-text bg-surface border-paper">
                <i class="bi bi-search"></i>
            </span>
            <input
                class="form-control"
                type="search"
                name="q"
                value="{{ $term }}"
                placeholder="Tìm theo tên khóa học hoặc môn học..."
            >
            <button class="btn btn-primary" type="submit">Tìm kiếm</button>
        </div>
    </form>

    <form method="GET" action="{{ route('student.search') }}" class="row g-2 mb-4">
        <input type="hidden" name="q" value="{{ $term }}">
        <div class="col-12 col-md-10">
            <select name="subject_id" class="form-select">
                <option value="">Tất cả môn học</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <button class="btn btn-outline-primary w-100" type="submit">Lọc môn học</button>
        </div>
    </form>

    <div class="d-flex flex-column gap-3">
        @forelse($courses as $course)
            <div class="card border rounded-3 overflow-hidden shadow-sm hover-shadow transition-all bg-white">
                <div class="row g-0 align-items-center">
                    <div class="col-md-4 col-lg-3 position-relative" style="min-height: 160px;">
                        <a href="{{ route('student.courses.show', $course) }}" class="d-block h-100 w-100">
                            <img src="{{ $course->cover_url }}" alt="{{ $course->title }}" class="w-100 h-100 object-fit-cover" style="min-height: 160px; max-height: 190px;">
                        </a>
                        <div class="position-absolute top-0 start-0 m-2">
                            <x-badge tone="primary">{{ $course->subject?->name }}</x-badge>
                        </div>
                    </div>
                    <div class="col-md-5 col-lg-6 p-3 p-lg-4">
                        <div class="d-flex align-items-center gap-2 mb-1 text-muted small">
                            <span><i class="bi bi-person-circle me-1"></i>{{ $course->teacher?->name ?: 'Giảng viên' }}</span>
                            <span>•</span>
                            <span><i class="bi bi-calendar-event me-1"></i>{{ $course->start_at?->format('d/m/Y') ?: 'Bắt đầu ngay' }} - {{ $course->end_at?->format('d/m/Y') ?: 'Vô thời hạn' }}</span>
                        </div>
                        <h2 class="h5 mb-2 fw-bold">
                            <a href="{{ route('student.courses.show', $course) }}" class="text-dark text-decoration-none hover-primary">
                                {{ $course->title }}
                            </a>
                        </h2>
                        <p class="small text-muted mb-0 text-truncate-2">
                            {{ Str::limit($course->description, 130) ?: 'Khóa học ôn thi THPT tuyển chọn kiến thức và tài liệu chi tiết.' }}
                        </p>
                    </div>
                    <div class="col-md-3 col-lg-3 p-3 p-lg-4 border-start-md bg-light d-flex flex-column gap-2 justify-content-center h-100" style="min-height: 160px;">
                        <a href="{{ route('student.courses.show', $course) }}" class="btn btn-primary btn-sm w-100 py-2">
                            <i class="bi bi-eye me-1"></i>Xem chi tiết khóa học
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <x-empty-state
                title="Không tìm thấy khóa học"
                message="Thử tìm với từ khóa khác."
                icon="search"
            />
        @endforelse
    </div>

    <div class="mt-4">{{ $courses->links('pagination::bootstrap-5') }}</div>
@endsection
