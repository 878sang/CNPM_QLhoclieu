@extends('layouts.student')

@section('content')
<div class="mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <a href="{{ route('student.courses.assignments', $course) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Danh sách bài tập
        </a>
        <a href="{{ route('student.courses.show', $course) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-book me-1"></i>Về khóa học
        </a>
    </div>
    <h1 class="h4 fw-bold text-dark mb-1">
        <i class="bi bi-clipboard-check text-success me-2"></i>Kết quả bài làm: {{ $assignment->title }}
    </h1>
    <p class="text-muted small mb-0">Xem chi tiết điểm số, nhận xét và bảng so sánh đáp án bài làm của bạn.</p>
</div>

@if(session('status'))
    <x-alert tone="success" class="mb-3">{{ session('status') }}</x-alert>
@endif

<div class="row g-4 mb-4">
    {{-- Card điểm số và thông tin nộp bài --}}
    <div class="col-lg-4">
        <div class="card border rounded-3 shadow-2xs p-4 bg-white h-100 text-center">
            <div class="mb-3">
                <span class="badge bg-success-subtle text-success border border-success px-3 py-1 fs-6">
                    <i class="bi bi-check-circle-fill me-1"></i>Đã nộp bài
                </span>
            </div>

            <div class="py-3">
                @if($submission->isGraded())
                    <div class="display-3 fw-bold text-primary mb-1">
                        {{ $submission->score }}
                    </div>
                    <div class="text-muted fw-semibold">
                        Thang điểm: {{ $submission->max_score ?: $assignment->maxScore() }}
                    </div>
                    <div class="mt-2">
                        @php
                            $max = $submission->max_score ?: $assignment->maxScore();
                            $percent = $max > 0 ? round(($submission->score / $max) * 100) : 0;
                        @endphp
                        <span class="badge {{ $percent >= 80 ? 'bg-success' : ($percent >= 50 ? 'bg-warning text-dark' : 'bg-danger') }} px-3 py-1">
                            {{ $percent }}% số điểm đạt được
                        </span>
                    </div>
                @else
                    <div class="display-6 fw-bold text-warning mb-1">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Chờ chấm điểm</h5>
                    <p class="small text-muted mb-0">Bài làm đang chờ giảng viên xem xét và chấm điểm chi tiết.</p>
                @endif
            </div>

            <div class="border-top pt-3 text-start small text-muted">
                <div class="d-flex justify-content-between mb-1">
                    <span><i class="bi bi-clock-history me-1"></i>Thời gian nộp:</span>
                    <strong>{{ $submission->submitted_at?->format('H:i - d/m/Y') }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span><i class="bi bi-ui-checks me-1"></i>Hình thức:</span>
                    <strong>{{ $assignment->isMultipleChoice() ? 'Trắc nghiệm' : 'Tự luận' }}</strong>
                </div>
                @if($submission->file_path)
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                        <span><i class="bi bi-paperclip me-1"></i>Tệp đính kèm:</span>
                        <a href="{{ route('student.assignments.download_file_student', [$course, $assignment]) }}" class="badge bg-light text-primary border text-decoration-none py-1">
                            {{ $submission->original_name ?: 'Tệp bài nộp' }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Card nhận xét của giảng viên & hướng dẫn --}}
    <div class="col-lg-8">
        <div class="card border rounded-3 shadow-2xs p-4 bg-white h-100">
            <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                <i class="bi bi-chat-left-quote text-primary me-2"></i>Nhận xét từ giảng viên
            </h5>

            @if($submission->feedback)
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <p class="mb-0 text-dark fst-italic">“{{ $submission->feedback }}”</p>
                    <div class="text-end small text-muted mt-2">
                        — Chấm bởi: <strong>{{ $submission->grader?->name ?: 'Giảng viên' }}</strong>
                        @if($submission->graded_at)
                            vào lúc {{ $submission->graded_at->format('H:i - d/m/Y') }}
                        @endif
                    </div>
                </div>
            @else
                <div class="p-4 bg-light rounded-3 border text-center text-muted mb-3">
                    <i class="bi bi-chat-square-dots fs-3 d-block mb-1 text-secondary"></i>
                    <p class="small mb-0">Chưa có nhận xét riêng từ giảng viên cho bài làm này.</p>
                </div>
            @endif

            {{-- Thông tin đề bài & đáp án tải về --}}
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto pt-3 border-top">
                @if($assignment->file_path)
                    <a href="{{ route('student.assignments.preview_file_student', [$course, $assignment]) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-file-earmark-text me-1"></i>Xem lại đề bài
                    </a>
                @endif
                @if($assignment->show_answer_to_students && $assignment->answer_file_path)
                    <a href="{{ route('student.assignments.answer_file', [$course, $assignment]) }}" target="_blank" class="btn btn-sm btn-success">
                        <i class="bi bi-file-earmark-check me-1"></i>Tải tệp lời giải chi tiết
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- BẢNG ĐỐI CHIẾU ĐÁP ÁN (NẾU GIẢNG VIÊN MỞ XEM ĐÁP ÁN) --}}
@if($assignment->show_answer_to_students && !empty($assignment->answer_key))
    <div class="card border rounded-3 shadow-2xs p-4 bg-white mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
            <div>
                <h5 class="fw-bold text-dark mb-1">
                    <i class="bi bi-check2-all text-success me-2"></i>Bảng đối chiếu đáp án chi tiết
                </h5>
                <p class="small text-muted mb-0">So sánh câu trả lời của bạn với đáp án chuẩn của giảng viên.</p>
            </div>
            @if($assignment->answer_file_path)
                <a href="{{ route('student.assignments.answer_file', [$course, $assignment]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-eye me-1"></i>Mở tệp giải thích chi tiết
                </a>
            @endif
        </div>

        <div class="row g-3">
            @foreach($assignment->answer_key as $question => $correct)
                @php
                    $studentAnswer = $submission->getAnswerFor((int) $question);
                    $isCorrect = $studentAnswer !== null && strtoupper(trim((string) $studentAnswer)) === strtoupper(trim((string) $correct));
                    $answered = $studentAnswer !== null && $studentAnswer !== '';
                @endphp
                <div class="col-md-6 col-xl-4">
                    <div class="card border rounded-3 p-3 h-100 {{ $isCorrect ? 'border-success bg-success-subtle bg-opacity-25' : ($answered ? 'border-danger bg-danger-subtle bg-opacity-25' : 'border-secondary-subtle bg-light') }}">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-bold text-dark">Câu {{ $question }}</span>
                            @if($isCorrect)
                                <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Đúng</span>
                            @elseif($answered)
                                <span class="badge bg-danger"><i class="bi bi-x-lg me-1"></i>Sai</span>
                            @else
                                <span class="badge bg-secondary">Chưa làm</span>
                            @endif
                        </div>
                        <div class="small">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Đáp án của bạn:</span>
                                <strong class="{{ $isCorrect ? 'text-success' : ($answered ? 'text-danger' : 'text-muted') }}">
                                    {{ $studentAnswer ?: '—' }}
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Đáp án đúng:</span>
                                <strong class="text-success">{{ $correct }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@endsection
