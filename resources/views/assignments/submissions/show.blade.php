@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')

@section('content')
<div class="mb-4">
    <div class="small text-muted mb-1">
        <a href="{{ route('courses.assignments.submissions.index', [$course, $assignment]) }}" class="text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i>Danh sách bài nộp – {{ $assignment->title }}
        </a>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center fs-4 shadow-sm" style="width: 48px; height: 48px;">
                {{ strtoupper(substr($submission->student?->name ?: 'H', 0, 1)) }}
            </div>
            <div>
                <h1 class="h4 fw-bold mb-0 text-dark">
                    Bài làm của: {{ $submission->student?->name ?: 'Học viên' }}
                </h1>
                <div class="small text-muted">
                    <span>{{ $submission->student?->email }}</span>
                    <span class="mx-2">·</span>
                    <span>Nộp lúc: <strong>{{ $submission->submitted_at?->format('H:i - d/m/Y') }}</strong></span>
                    @if($assignment->deadline && $submission->submitted_at && $submission->submitted_at->gt($assignment->deadline))
                        <span class="badge bg-danger-subtle text-danger ms-2">Nộp muộn</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('courses.assignments.submissions.index', [$course, $assignment]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Danh sách bài nộp
            </a>
            <a href="{{ route('courses.assignments.show', [$course, $assignment]) }}" class="btn btn-outline-primary">
                <i class="bi bi-journal-text me-1"></i>Chi tiết bài tập
            </a>
        </div>
    </div>
</div>

@if(session('status'))
    <x-alert tone="success" class="mb-3">{{ session('status') }}</x-alert>
@endif
@if($errors->any())
    <div class="alert alert-danger border-0 shadow-2xs mb-3">
        <ul class="mb-0 ps-3 small">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    {{-- CỘT TRÁI: Điểm số, Thông tin bài nộp & Form chấm điểm --}}
    <div class="col-lg-4 col-xl-4">
        {{-- Card điểm số hiện tại --}}
        <div class="card border rounded-3 shadow-2xs p-4 bg-white mb-3 text-center">
            <div class="small text-muted text-uppercase fw-semibold mb-1">Điểm số bài làm</div>
            @if($submission->isGraded())
                <div class="display-4 fw-bold text-primary mb-1">
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
                    <span class="badge {{ $percent >= 80 ? 'bg-success' : ($percent >= 50 ? 'bg-warning text-dark' : 'bg-danger') }} px-3 py-1 fs-6">
                        {{ $percent }}% số điểm
                    </span>
                </div>
            @else
                <div class="py-3">
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 fs-6">
                        <i class="bi bi-hourglass-split me-1"></i>Chưa chấm điểm
                    </span>
                </div>
            @endif
        </div>

        {{-- Card Chấm điểm & Nhận xét --}}
        <div class="card border rounded-3 shadow-2xs p-4 bg-white mb-3">
            <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                <i class="bi bi-pencil-square text-primary me-2"></i>Chấm điểm &amp; Nhận xét
            </h5>

            <form method="POST" action="{{ route('courses.assignments.submissions.grade', [$course, $assignment, $submission]) }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold small">
                        Điểm số <span class="text-danger">*</span>
                        <span class="text-muted fw-normal">(Tối đa {{ $assignment->maxScore() }} điểm)</span>
                    </label>
                    <input type="number"
                        name="score"
                        step="0.01"
                        min="0"
                        max="{{ $assignment->maxScore() }}"
                        class="form-control form-control-lg fw-bold text-primary"
                        value="{{ old('score', $submission->score) }}"
                        placeholder="VD: 8.5"
                        required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Lời phê / Nhận xét của giáo viên</label>
                    <textarea name="feedback"
                        rows="4"
                        class="form-control"
                        placeholder="Nhập nhận xét, hướng dẫn sửa bài hoặc động viên học viên...">{{ old('feedback', $submission->feedback) }}</textarea>
                </div>

                @if($submission->isGraded())
                    <div class="small text-muted mb-3 p-2 bg-light rounded border">
                        <i class="bi bi-check-circle-fill text-success me-1"></i>
                        Đã chấm bởi: <strong>{{ $submission->grader?->name ?: 'Giáo viên' }}</strong>
                        @if($submission->graded_at)
                            vào lúc {{ $submission->graded_at->format('H:i - d/m/Y') }}
                        @endif
                    </div>
                @endif

                <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                    <i class="bi bi-floppy me-1"></i>{{ $submission->isGraded() ? 'Cập nhật điểm & nhận xét' : 'Lưu kết quả chấm điểm' }}
                </button>
            </form>
        </div>

        {{-- Card Tệp bài làm học viên tải lên (nếu có) --}}
        @if($submission->file_path)
            <div class="card border rounded-3 shadow-2xs p-3 bg-white mb-3">
                <h6 class="fw-bold text-dark mb-2">
                    <i class="bi bi-paperclip text-primary me-1"></i>Tệp đính kèm của học viên
                </h6>
                <div class="p-2 bg-light rounded border mb-2 d-flex align-items-center justify-content-between">
                    <span class="text-truncate small fw-semibold me-2" title="{{ $submission->original_name }}">
                        <i class="bi bi-file-earmark-text text-primary me-1"></i>{{ $submission->original_name ?: 'bai-nop' }}
                    </span>
                    <a href="{{ route('courses.assignments.submissions.download', [$course, $assignment, $submission]) }}" class="btn btn-sm btn-outline-secondary py-0">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
                <div class="d-grid gap-2">
                    <a href="{{ route('courses.assignments.submissions.preview_file', [$course, $assignment, $submission]) }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Mở xem tệp trong tab mới
                    </a>
                </div>
            </div>
        @endif
    </div>

    {{-- CỘT PHẢI: Bảng chi tiết câu trả lời của học viên đối chiếu với đáp án --}}
    <div class="col-lg-8 col-xl-8">
        <div class="card border rounded-3 shadow-2xs p-4 bg-white mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                <div>
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-list-check text-success me-2"></i>Chi tiết câu trả lời của học viên
                    </h5>
                    <small class="text-muted">Tổng số câu hỏi: {{ $assignment->question_count }} câu · Hình thức: {{ $assignment->typeLabel() }}</small>
                </div>
                <div class="d-flex align-items-center gap-2 small">
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Xanh lá: Đúng</span>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Đỏ: Sai</span>
                    <span class="badge bg-secondary-subtle text-secondary border">Xám: Chưa làm</span>
                </div>
            </div>

            {{-- Lưới so sánh từng câu hỏi --}}
            <div class="row g-2 g-md-3">
                @for($i = 1; $i <= $assignment->question_count; $i++)
                    @php
                        $isMC = $assignment->isMultipleChoiceQuestion($i);
                        $studentAns = $submission->getAnswerFor($i);
                        $correctAns = $assignment->getAnswerFor($i);
                        $hasAnswer = $studentAns !== null && $studentAns !== '';
                        $isCorrect = false;
                        if ($correctAns !== null && $hasAnswer) {
                            $isCorrect = strtoupper(trim((string) $studentAns)) === strtoupper(trim((string) $correctAns));
                        }
                    @endphp
                    <div class="col-sm-6 mb-2">
                        <div class="p-3 rounded-2 border h-100 {{ $correctAns !== null ? ($isCorrect ? 'bg-success-subtle bg-opacity-25 border-success' : ($hasAnswer ? 'bg-danger-subtle bg-opacity-25 border-danger' : 'bg-light border-secondary-subtle')) : 'bg-light' }}">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold text-dark">Câu {{ $i }}</span>
                                    <span class="badge {{ $isMC ? 'bg-success text-white' : 'bg-primary text-white' }} py-0 px-2" style="font-size: 0.7rem;">
                                        {{ $isMC ? 'Trắc nghiệm' : 'Điền số' }}
                                    </span>
                                </div>
                                @if($correctAns !== null)
                                    @if($isCorrect)
                                        <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Đúng</span>
                                    @elseif($hasAnswer)
                                        <span class="badge bg-danger"><i class="bi bi-x-lg me-1"></i>Sai</span>
                                    @else
                                        <span class="badge bg-secondary">Chưa làm</span>
                                    @endif
                                @else
                                    <span class="badge bg-light text-dark border">Chưa có key</span>
                                @endif
                            </div>

                            <div class="small">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted">Học viên chọn:</span>
                                    <strong class="{{ $correctAns !== null ? ($isCorrect ? 'text-success' : ($hasAnswer ? 'text-danger' : 'text-muted')) : 'text-dark' }} fs-6">
                                        {{ $hasAnswer ? $studentAns : '—' }}
                                    </strong>
                                </div>
                                @if($correctAns !== null)
                                    <div class="d-flex justify-content-between align-items-center pt-1 border-top">
                                        <span class="text-muted">Đáp án chuẩn:</span>
                                        <strong class="text-success">{{ $correctAns }}</strong>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        {{-- Xem trước tệp đính kèm (nếu học viên có tải lên) --}}
        @if($submission->file_path)
            @php
                $ext = strtolower(pathinfo($submission->original_name ?? '', PATHINFO_EXTENSION));
            @endphp
            <div class="card border rounded-3 shadow-2xs overflow-hidden bg-white mb-4">
                <div class="card-header bg-light d-flex align-items-center justify-content-between py-2 px-3">
                    <span class="fw-semibold small text-dark">
                        <i class="bi bi-file-earmark-text text-primary me-1"></i>Xem trước tệp bài làm: <strong>{{ $submission->original_name }}</strong>
                    </span>
                    <a href="{{ route('courses.assignments.submissions.download', [$course, $assignment, $submission]) }}" class="btn btn-sm btn-outline-secondary py-0">
                        <i class="bi bi-download me-1"></i>Tải về
                    </a>
                </div>
                <div class="card-body p-2 bg-dark d-flex justify-content-center align-items-center" style="min-height: 400px; max-height: 75vh; overflow: auto;">
                    @if($ext === 'pdf')
                        <iframe src="{{ route('courses.assignments.submissions.preview_file', [$course, $assignment, $submission]) }}" title="Bài nộp" class="w-100 bg-white rounded border-0" style="height: 70vh; min-height: 480px;"></iframe>
                    @elseif(in_array($ext, ['jpg', 'jpeg', 'png', 'webp']))
                        <img src="{{ route('courses.assignments.submissions.preview_file', [$course, $assignment, $submission]) }}" alt="Bài nộp" class="img-fluid rounded" style="max-height: 70vh; object-fit: contain;">
                    @else
                        <div class="text-center p-4 text-white">
                            <i class="bi bi-file-earmark-word fs-1 text-primary mb-2 d-block"></i>
                            <h6>{{ $submission->original_name }}</h6>
                            <p class="small text-white-50 mb-3">Tệp này cần được tải về máy để mở xem.</p>
                            <a class="btn btn-primary btn-sm px-3" href="{{ route('courses.assignments.submissions.download', [$course, $assignment, $submission]) }}">
                                <i class="bi bi-download me-1"></i>Tải tệp bài nộp
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
