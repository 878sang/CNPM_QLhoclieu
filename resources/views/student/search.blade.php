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

    <div class="row g-4">
        @forelse($courses as $course)
            <div class="col-md-6 col-xl-4">
                <x-card class="p-3 h-100 lm-card-hover">
                    <span class="student-course-cover">
                        <i class="bi bi-book-half"></i>
                    </span>
                    <x-badge class="mt-3" tone="primary">
                        {{ $course->subject?->name }}
                    </x-badge>
                    <h2 class="h5 mt-3">{{ $course->title }}</h2>
                    <p class="small lm-text-muted">
                        {{ Str::limit($course->description, 110) }}
                    </p>
                    <span class="small text-brand">
                        <i class="bi bi-check2-circle me-1"></i>
                        Đang mở
                    </span>
                </x-card>
            </div>
        @empty
            <div class="col-12">
                <x-empty-state
                    title="Không tìm thấy khóa học"
                    message="Thử tìm với từ khóa khác."
                    icon="search"
                />
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $courses->links('pagination::bootstrap-5') }}</div>
@endsection
