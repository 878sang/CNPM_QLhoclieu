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

    <div class="row g-4">
        @forelse($courses as $course)
            <div class="col-md-6 col-xl-4">
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
                    <small class="text-muted">Chưa bắt đầu</small>
                </x-card>
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
