@extends('layouts.student')

@section('content')
    <x-page-header
        eyebrow="KHÔNG GIAN HỌC TẬP"
        title="Khóa học của tôi"
        description="Tiếp tục ôn tập từ nơi bạn đã dừng lại."
    >
        <x-slot:actions>
            <x-button :href="route('student.search')" variant="outline-primary">
                <i class="bi bi-search me-2"></i>
                Tìm học liệu
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-4">
        @forelse($courses as $course)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('student.courses.show', $course) }}" class="text-decoration-none text-reset">
                <x-card class="p-3 h-100 lm-card-hover">
                    <span class="student-course-cover">
                        <i class="bi bi-journal-bookmark"></i>
                    </span>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <x-badge tone="primary">
                            {{ $course->subject?->name }}
                        </x-badge>
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
                    message="Hãy tìm học liệu phù hợp hoặc liên hệ giảng viên để được thêm vào khóa học."
                    icon="journal-bookmark"
                />

                <div class="text-center">
                    <x-button :href="route('student.search')">
                        Tìm khóa học
                    </x-button>
                </div>
            </div>
        @endforelse
    </div>
@endsection
