@extends('layouts.student')

@section('content')
    <x-page-header
        eyebrow="HỌC TẬP"
        title="Khóa học của tôi"
        description="Các khóa học bạn đang tham gia cùng tiến độ học tập chi tiết."
    >
        <x-slot:actions>
            <x-button :href="route('student.search')" variant="outline-primary">
                <i class="bi bi-search me-2"></i>
                Tìm học liệu
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <x-alert tone="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <form method="GET" action="{{ route('student.courses') }}" class="row g-2 mb-4">
        <div class="col-12 col-md-6">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tìm theo tên khóa học hoặc môn học">
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

    <div class="d-flex flex-column gap-3">
        @forelse($courses as $course)
            @php
                $user = auth()->user();
                $percent = $course->progressPercentageFor($user);
                $statusTone = $course->progressStatusTone($user);
                $statusLabel = $course->progressStatusLabel($user);
                $lastLesson = $course->lastStudiedLessonFor($user);
                $totalLessons = $course->chapters->sum(fn ($c) => $c->lessons->count());
                $completedLessons = $course->progresses->where('is_completed', true)->count();
            @endphp
            <div class="card border rounded-3 overflow-hidden shadow-sm hover-shadow transition-all bg-white">
                <div class="row g-0 align-items-center">
                    <!-- Ảnh bìa ô ngang (16:9) -->
                    <div class="col-md-4 col-lg-3 position-relative" style="min-height: 170px;">
                        <a href="{{ route('student.courses.show', $course) }}" class="d-block h-100 w-100">
                            <img src="{{ $course->cover_url }}" alt="{{ $course->title }}" class="w-100 h-100 object-fit-cover" style="min-height: 170px; max-height: 200px;">
                        </a>
                        <div class="position-absolute top-0 start-0 m-2">
                            <x-badge tone="primary">{{ $course->subject?->name }}</x-badge>
                        </div>
                        <div class="position-absolute top-0 end-0 m-2">
                            <x-badge :tone="$statusTone">{{ $statusLabel }}</x-badge>
                        </div>
                    </div>

                    <!-- Nội dung khóa học -->
                    <div class="col-md-5 col-lg-6 p-3 p-lg-4">
                        <div class="d-flex align-items-center gap-2 mb-1 text-muted small">
                            <span><i class="bi bi-person-circle me-1"></i>{{ $course->teacher?->name ?: 'Giảng viên' }}</span>
                            <span>•</span>
                            <span><i class="bi bi-clock-history me-1"></i>{{ $totalLessons }} bài học</span>
                        </div>

                        <h2 class="h5 mb-2 fw-bold">
                            <a href="{{ route('student.courses.show', $course) }}" class="text-dark text-decoration-none hover-primary">
                                {{ $course->title }}
                            </a>
                        </h2>

                        <p class="small text-muted mb-3 text-truncate-2">
                            {{ Str::limit($course->description, 130) ?: 'Khóa học ôn luyện trọng tâm kiến thức và bài tập.' }}
                        </p>

                        <!-- Thanh tiến độ học tập -->
                        <div>
                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                <span class="text-muted"><i class="bi bi-check2-circle text-success me-1"></i>Đã học: <strong>{{ $completedLessons }}/{{ $totalLessons }} bài</strong></span>
                                <span class="fw-bold text-primary">{{ $percent }}%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percent }}%" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                    <h2 class="h5 mt-3">{{ $course->title }}</h2>
                    <p class="small lm-text-muted mb-0">
                        {{ Str::limit($course->description, 100) }}
                    </p>
                    <div class="progress lm-progress mt-4">
                        <div class="progress-bar" style="width: {{ $course->progress_percent }}%"></div>
                    </div>
                    <small class="text-muted">
                        @if ($course->start_at?->isFuture())
                            Khóa học chưa bắt đầu
                        @elseif ($course->trackable_documents_count > 0)
                            {{ $course->progress_percent }}% · {{ $course->completed_documents_count }}/{{ $course->trackable_documents_count }} học liệu đã hoàn thành
                        @else
                            Khóa học chưa có học liệu
                        @endif
                    </small>
                </x-card>
                </a>
                <form method="POST" action="{{ route('student.courses.leave', $course) }}" class="mt-2" onsubmit="return confirm('Bạn có chắc muốn rời khỏi khóa học này?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">Rời khóa học</button>
                </form>

                    <!-- Nút thao tác học tập -->
                    <div class="col-md-3 col-lg-3 p-3 p-lg-4 border-start-md bg-light d-flex flex-column gap-2 justify-content-center h-100" style="min-height: 170px;">
                        <a href="{{ route('student.courses.learn', [$course, $lastLesson?->id]) }}" class="btn btn-primary btn-sm w-100 py-2 fw-semibold">
                            <i class="bi bi-play-circle-fill me-1"></i>{{ $percent > 0 ? 'Tiếp tục bài học' : 'Bắt đầu học ngay' }}
                        </a>
                        <a href="{{ route('student.courses.show', $course) }}" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="bi bi-info-circle me-1"></i>Xem chi tiết khóa học
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <x-empty-state
                title="Bạn chưa tham gia khóa học nào"
                message="Hãy tìm học liệu phù hợp hoặc liên hệ giảng viên để được ghi danh vào lớp."
                icon="journal-bookmark"
            />
        @endforelse
    </div>
    <div class="mt-4">{{ $courses->links('pagination::bootstrap-5') }}</div>
@endsection
