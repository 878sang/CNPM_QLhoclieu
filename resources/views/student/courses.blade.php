@extends('layouts.student')

@section('content')
    <x-page-header
        eyebrow="HỌC TẬP"
        title="Khóa học của tôi"
        description="Các khóa học bạn đã được thêm vào."
    >
        <x-slot:actions>
            <x-button :href="route('student.search')" variant="outline-primary">
                <i class="bi bi-search me-2"></i>
                Tìm học liệu
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('student.courses') }}" class="row g-2 mb-4">
        <div class="col-12 col-md-6">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tìm theo tên hoặc môn học">
        </div>
        <div class="col-12 col-md-4">
            <select name="subject_id" class="form-select">
                <option value="">Tất cả môn học</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Lọc</button>
            <a href="{{ route('student.courses') }}" class="btn btn-outline-secondary">Xóa</a>
        </div>
    </form>

    <div class="row g-4">
        @forelse($courses as $course)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('student.courses.show', $course) }}" class="text-decoration-none text-reset">
                <x-card class="p-3 h-100 lm-card-hover">
                    <span class="student-course-cover">
                        <i class="bi bi-journal-bookmark"></i>
                    </span>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <x-badge tone="primary">{{ $course->subject?->name }}</x-badge>
                        <span class="small text-muted">Đang học</span>
                    </div>
                    <h2 class="h5 mt-3">{{ $course->title }}</h2>
                    <p class="small lm-text-muted mb-0">
                        {{ Str::limit($course->description, 100) }}
                    </p>
                    <div class="progress lm-progress mt-4">
                        <div class="progress-bar" style="width: 0%"></div>
                    </div>
                    <small class="text-muted">
                        @if ($course->start_at?->isFuture())
                            Khóa học chưa bắt đầu
                        @else
                            Chưa có dữ liệu tiến độ
                        @endif
                    </small>
                </x-card>
                </a>
                <form method="POST" action="{{ route('student.courses.leave', $course) }}" class="mt-2" onsubmit="return confirm('Bạn có chắc muốn rời khỏi khóa học này?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">Rời khóa học</button>
                </form>
            </div>
        @empty
            <div class="col-12">
                <x-empty-state
                    title="Bạn chưa tham gia khóa học"
                    message="Hãy tìm học liệu phù hợp hoặc liên hệ giảng viên."
                    icon="journal-bookmark"
                />
            </div>
        @endforelse
    </div>
    <div class="mt-4">{{ $courses->links('pagination::bootstrap-5') }}</div>
@endsection
