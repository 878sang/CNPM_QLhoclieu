@extends('layouts.student')

@section('content')
    <x-page-header
        eyebrow="KHÔNG GIAN HỌC TẬP"
        title="Khóa học của tôi"
        description="Tiếp tục học tập từ nơi bạn đã dừng lại và theo dõi tiến độ từng môn."
    >
        <x-slot:actions>
            <div class="d-flex flex-wrap gap-2">
                <x-button :href="route('student.courses')" variant="outline-secondary">
                    <i class="bi bi-grid me-1"></i>Xem tất cả khóa học
                </x-button>
                <x-button :href="route('student.search')" variant="primary">
                    <i class="bi bi-search me-1"></i>Tìm học liệu
                </x-button>
            </div>
        </x-slot:actions>
    </x-page-header>

    <div class="d-flex flex-column gap-3">
        @php
            $user = auth()->user();
        @endphp
        @forelse ($courses as $course)
            @php
                $percent = $course->progressPercentageFor($user);
                $statusTone = $course->progressStatusTone($user);
                $statusLabel = $course->progressStatusLabel($user);
                $lastLesson = $course->lastStudiedLessonFor($user);
                $totalLessons = $course->chapters->sum(fn ($c) => $c->lessons->count());
                $completedLessons = $course->progresses->where('is_completed', true)->count();
            @endphp
            <div class="lm-card overflow-hidden lm-card-hover">
                <div class="row g-0 align-items-stretch">
                    <div class="col-md-4 col-lg-3 position-relative lm-course-thumb">
                        <a href="{{ route('student.courses.show', $course) }}" class="d-block h-100 w-100">
                            <img src="{{ $course->cover_url }}" alt="{{ $course->title }}" class="w-100 h-100 object-fit-cover">
                        </a>
                        <div class="position-absolute top-0 start-0 m-2 d-flex flex-wrap gap-1">
                            <x-badge tone="brand">{{ $course->subject?->name }}</x-badge>
                        </div>
                        <div class="position-absolute top-0 end-0 m-2">
                            <x-badge :tone="$statusTone">{{ $statusLabel }}</x-badge>
                        </div>
                    </div>

                    <div class="col-md-5 col-lg-6 p-3 p-lg-4 d-flex flex-column justify-content-between min-w-0">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1 text-muted small">
                                <span><i class="bi bi-person-circle me-1"></i>{{ $course->teacher?->name ?: 'Giảng viên' }}</span>
                                <span>•</span>
                                <span><i class="bi bi-journal-bookmark me-1"></i>{{ $totalLessons }} bài học</span>
                            </div>
                            <h2 class="h5 mb-2 fw-bold text-truncate">
                                <a href="{{ route('student.courses.show', $course) }}" class="text-decoration-none hover-primary lm-title-link">
                                    {{ $course->title }}
                                </a>
                            </h2>
                            <p class="small text-muted mb-3 text-truncate-2">
                                {{ Str::limit($course->description, 130) ?: 'Khóa học ôn luyện trọng tâm kiến thức, video bài giảng và bài tập.' }}
                            </p>
                        </div>

                        <div>
                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                <span class="text-muted">
                                    <i class="bi bi-check2-circle text-success me-1"></i>
                                    Đã học: <strong>{{ $completedLessons }}/{{ $totalLessons }} bài</strong>
                                </span>
                                <span class="fw-bold text-brand font-mono">{{ $percent }}%</span>
                            </div>
                            <div class="progress lm-progress">
                                <div class="progress-bar" role="progressbar" style="width: {{ $percent }}%" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            @if ($course->start_at?->isFuture())
                                <small class="text-muted d-block mt-1">Khóa học chưa bắt đầu</small>
                            @elseif (($course->trackable_documents_count ?? 0) > 0)
                                <small class="text-muted d-block mt-1">
                                    {{ $course->progress_percent }}% · {{ $course->completed_documents_count }}/{{ $course->trackable_documents_count }} học liệu đã hoàn thành
                                </small>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-3 col-lg-3 p-3 p-lg-4 border-start-md lm-card-aside d-flex flex-column gap-2 justify-content-center">
                        <a href="{{ route('student.courses.learn', [$course, $lastLesson?->id]) }}" class="btn btn-primary btn-sm w-100 fw-semibold">
                            <i class="bi bi-play-circle-fill me-1"></i>{{ $percent > 0 ? 'Tiếp tục học' : 'Bắt đầu học' }}
                        </a>
                        <a href="{{ route('student.courses.show', $course) }}" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="bi bi-info-circle me-1"></i>Xem chi tiết
                        </a>
                        <form method="POST" action="{{ route('student.courses.leave', $course) }}" onsubmit="return confirm('Bạn có chắc muốn rời khỏi khóa học này?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-link btn-sm text-danger text-decoration-none w-100 py-1">
                                <i class="bi bi-box-arrow-left me-1"></i>Rời khóa học
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <x-card class="p-4">
                <x-empty-state
                    title="Bạn chưa tham gia khóa học nào"
                    message="Hãy tìm học liệu phù hợp hoặc liên hệ giảng viên để được thêm vào khóa học."
                    icon="journal-bookmark"
                />
                <div class="text-center mt-2">
                    <x-button :href="route('student.search')">
                        <i class="bi bi-search me-1"></i>Tìm khóa học ngay
                    </x-button>
                </div>
            </x-card>
        @endforelse
    </div>
@endsection
