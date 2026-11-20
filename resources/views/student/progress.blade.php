@extends('layouts.student')

@section('content')
    <x-page-header
        eyebrow="HỌC TẬP"
        title="Tiến độ học tập"
        description="Theo dõi chi tiết mức độ hoàn thành bài học và học liệu của từng khóa học."
    />

    <form method="GET" action="{{ route('student.progress') }}" class="row g-2 mb-4">
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
            <a href="{{ route('student.progress') }}" class="btn btn-outline-secondary">Xóa</a>
        </div>
    </form>

    <x-card class="p-3 p-md-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead>
                    <tr>
                        <th style="min-width: 220px;">Khóa học</th>
                        <th>Môn học</th>
                        <th>Trạng thái</th>
                        <th style="min-width: 210px;">Tiến độ xem (%)</th>
                        <th class="text-center" style="min-width: 165px;">Điểm tương tác video</th>
                        <th class="text-end">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $user = auth()->user();
                    @endphp
                    @forelse($courses as $course)
                        @php
                            $percent = $course->progressPercentageFor($user);
                            $effectivePercent = max($percent, (int) $course->progress_percent);
                            $effectiveTone = $effectivePercent >= 100 ? 'success' : ($effectivePercent > 0 ? 'primary' : 'secondary');
                            $effectiveLabel = $effectivePercent >= 100 ? 'Hoàn thành' : ($effectivePercent > 0 ? 'Đang học' : 'Chưa bắt đầu');
                            $totalLessons = $course->chapters->sum(fn ($c) => $c->lessons->count());
                            $completedLessons = $course->progresses->where('is_completed', true)->count();
                            $lastLesson = $course->lastStudiedLessonFor($user);
                            $videoQuiz = $course->videoInteractionStatsFor($user);
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <div class="rounded overflow-hidden border flex-shrink-0" style="width: 68px; height: 42px;">
                                        <img src="{{ $course->cover_url }}" alt="{{ $course->title }}" class="w-100 h-100 object-fit-cover">
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate" style="max-width: 240px;">
                                            <a href="{{ route('student.courses.show', $course) }}" class="text-decoration-none hover-primary lm-title-link">
                                                {{ $course->title }}
                                            </a>
                                        </div>
                                        <div class="small text-muted text-truncate">{{ $course->teacher?->name ?: 'Giảng viên' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-nowrap">
                                <x-badge tone="brand">{{ $course->subject?->name }}</x-badge>
                            </td>
                            <td class="text-nowrap">
                                <x-badge :tone="$effectiveTone">
                                    {{ $effectiveLabel }}
                                </x-badge>
                            </td>
                            <td>
                                <div class="d-flex justify-content-between align-items-center small mb-1">
                                    <span class="text-muted">{{ $completedLessons }}/{{ $totalLessons }} bài học</span>
                                    <span class="fw-bold text-brand font-mono">{{ $effectivePercent }}%</span>
                                </div>
                                <div class="progress lm-progress" role="progressbar" aria-label="Tiến độ {{ $course->title }}" aria-valuenow="{{ $effectivePercent }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: {{ $effectivePercent }}%"></div>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    @if (($course->trackable_documents_count ?? 0) > 0)
                                        {{ $course->progress_percent }}% · {{ $course->completed_documents_count }}/{{ $course->trackable_documents_count }} học liệu
                                    @else
                                        Khóa học chưa có học liệu
                                    @endif
                                </small>
                            </td>
                            <td class="text-center">
                                @if($videoQuiz['total'] > 0)
                                    @if($videoQuiz['answered'] > 0)
                                        <span class="badge {{ $videoQuiz['score_10'] >= 8 ? 'bg-success' : ($videoQuiz['score_10'] >= 5 ? 'bg-warning text-dark' : 'bg-danger') }} font-mono">
                                            {{ number_format($videoQuiz['score_10'], 1) }}/10 điểm
                                        </span>
                                        <div class="small text-muted mt-1">
                                            Đúng {{ $videoQuiz['correct'] }}/{{ $videoQuiz['total'] }} câu
                                        </div>
                                    @else
                                        <span class="badge bg-light text-muted border">Chưa làm</span>
                                        <div class="small text-muted mt-1">0/{{ $videoQuiz['total'] }} câu</div>
                                    @endif
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('student.courses.learn', [$course, $lastLesson?->id]) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-play-circle me-1"></i>{{ $percent > 0 ? 'Tiếp tục học' : 'Bắt đầu' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state
                                    title="Chưa có dữ liệu tiến độ"
                                    message="Bạn chưa tham gia khóa học nào. Hãy đăng ký khóa học để bắt đầu theo dõi tiến độ."
                                    icon="bar-chart"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
