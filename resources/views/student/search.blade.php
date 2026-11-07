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

    <div class="row g-4">
        @forelse($courses as $course)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('student.courses.show', $course) }}" class="text-decoration-none text-reset">
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
                </a>
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
