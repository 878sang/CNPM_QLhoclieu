@extends('layouts.student')

@section('content')
<div class="mb-4">
    <div class="small text-muted mb-1">
        <a href="{{ route('student.courses.show', $course) }}" class="text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i>{{ $course->title }}
        </a>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="h4 fw-bold text-dark mb-1">
                <i class="bi bi-journals text-primary me-2"></i>Danh sách bài tập khóa học
            </h1>
            <p class="text-muted small mb-0">Hoàn thành các bài tập và nộp đáp án đúng hạn để củng cố kiến thức.</p>
        </div>
        <a href="{{ route('student.courses.learn', $course) }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-play-circle me-1"></i>Vào học bài giảng
        </a>
    </div>
</div>

@if(session('status'))
    <x-alert tone="success" class="mb-3">{{ session('status') }}</x-alert>
@endif
@if(session('error'))
    <x-alert tone="danger" class="mb-3">{{ session('error') }}</x-alert>
@endif

<div class="d-flex flex-column gap-3 mb-4">
    @forelse ($assignments as $assignment)
        @php
            $submitted = $assignment->my_submission !== null;
            $sub = $assignment->my_submission;
            $isPastDeadline = $assignment->isPastDeadline();
        @endphp
        <div class="card border rounded-3 shadow-2xs overflow-hidden bg-white hover-shadow transition-all">
            <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="flex-grow-1" style="max-width: 680px;">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="badge {{ $assignment->isMultipleChoice() ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-info-subtle text-info-emphasis border border-info-subtle' }} fw-semibold">
                            {{ $assignment->isMultipleChoice() ? 'Trắc nghiệm ABCD' : 'Tự luận / Nhập số' }}
                        </span>
                        @if($assignment->lesson)
                            <span class="badge bg-secondary-subtle text-secondary border">
                                <i class="bi bi-bookmark me-1"></i>{{ $assignment->lesson->title }}
                            </span>
                        @endif
                        @if($submitted)
                            <span class="badge bg-success-subtle text-success border border-success fw-bold">
                                <i class="bi bi-check-circle-fill me-1"></i>Đã nộp bài
                            </span>
                        @elseif($isPastDeadline)
                            <span class="badge bg-danger-subtle text-danger border border-danger">
                                <i class="bi bi-clock-history me-1"></i>Đã hết hạn
                            </span>
                        @else
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning">
                                <i class="bi bi-hourglass-split me-1"></i>Chưa làm
                            </span>
                        @endif
                    </div>

                    <h3 class="h5 fw-bold text-dark mb-1">{{ $assignment->title }}</h3>

                    @if($assignment->description)
                        <p class="text-muted small mb-3 text-truncate-2">{{ $assignment->description }}</p>
                    @endif

                    <div class="d-flex flex-wrap align-items-center gap-3 small text-muted">
                        <span><i class="bi bi-list-ol text-primary me-1"></i><strong>{{ $assignment->question_count }}</strong> câu hỏi</span>
                        <span>
                            <i class="bi bi-clock text-secondary me-1"></i>
                            {{ $assignment->time_limit_minutes ? $assignment->time_limit_minutes.' phút' : 'Không giới hạn thời gian' }}
                        </span>
                        <span>
                            <i class="bi bi-calendar-event {{ $isPastDeadline ? 'text-danger' : 'text-secondary' }} me-1"></i>
                            Hạn nộp: <strong class="{{ $isPastDeadline ? 'text-danger' : '' }}">{{ $assignment->deadline ? $assignment->deadline->format('H:i - d/m/Y') : 'Không giới hạn' }}</strong>
                        </span>
                    </div>
                </div>

                <div class="text-end flex-shrink-0 d-flex flex-column align-items-end gap-2">
                    @if($submitted)
                        @if($sub->isGraded())
                            <div class="small fw-semibold text-muted">Điểm số:</div>
                            <div class="badge bg-success px-3 py-2 fs-6 fw-bold">
                                {{ $sub->score }} / {{ $sub->max_score ?: $assignment->maxScore() }}
                            </div>
                        @else
                            <div class="badge bg-secondary px-3 py-2 small">Đang chờ chấm</div>
                        @endif
                        <a class="btn btn-sm btn-outline-primary px-3" href="{{ route('student.assignments.result', [$course, $assignment]) }}">
                            <i class="bi bi-eye me-1"></i>Xem kết quả
                        </a>
                    @else
                        @if($isPastDeadline)
                            <button type="button" class="btn btn-secondary px-4 disabled">
                                <i class="bi bi-lock me-1"></i>Hết hạn nộp
                            </button>
                        @else
                            <a class="btn btn-primary px-4 fw-bold" href="{{ route('student.assignments.do', [$course, $assignment]) }}">
                                <i class="bi bi-pencil-square me-1"></i>Bắt đầu làm bài
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @empty
        <x-empty-state title="Chưa có bài tập nào" message="Hiện tại giảng viên chưa mở bài tập nào cho khóa học này." icon="journal-x" />
    @endforelse
</div>
@endsection
