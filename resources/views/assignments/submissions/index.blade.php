@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <div class="small text-muted mb-1">
            <a href="{{ route('courses.assignments.show', [$course, $assignment]) }}" class="text-decoration-none text-muted">
                <i class="bi bi-arrow-left me-1"></i>{{ $assignment->title }}
            </a>
        </div>
        <h1 class="h4 fw-bold mb-0 text-dark">
            <i class="bi bi-people text-primary me-2"></i>Danh sách bài nộp của học viên
        </h1>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('courses.assignments.show', [$course, $assignment]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Quay lại bài tập
        </a>
    </div>
</div>

@if(session('status'))
    <x-alert tone="success" class="mb-3">{{ session('status') }}</x-alert>
@endif
@if(session('error'))
    <x-alert tone="danger" class="mb-3">{{ session('error') }}</x-alert>
@endif

<div class="card border rounded-3 shadow-2xs bg-white overflow-hidden">
    <div class="card-header bg-light d-flex flex-wrap align-items-center justify-content-between gap-2 py-3 px-4">
        <div>
            <h5 class="fw-bold mb-0 text-dark">Tất cả bài nộp ({{ $submissions->count() }})</h5>
            <small class="text-muted">Tổng số câu: {{ $assignment->question_count }} câu · Điểm tối đa: {{ $assignment->maxScore() }} điểm</small>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Học viên</th>
                    <th>Thời gian nộp</th>
                    <th>Điểm số</th>
                    <th>Nhận xét &amp; Chấm điểm</th>
                    <th class="text-end pe-4">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($submissions as $submission)
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                    {{ strtoupper(substr($submission->student?->name ?: 'H', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $submission->student?->name ?: 'Học viên' }}</div>
                                    <div class="small text-muted">{{ $submission->student?->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="small fw-semibold text-dark">{{ $submission->submitted_at?->format('H:i - d/m/Y') }}</div>
                            @if($assignment->deadline && $submission->submitted_at && $submission->submitted_at->gt($assignment->deadline))
                                <span class="badge bg-danger-subtle text-danger small">Nộp muộn</span>
                            @endif
                        </td>
                        <td>
                            @if($submission->isGraded())
                                <span class="badge bg-success-subtle text-success border border-success fw-bold px-2 py-1 fs-6">
                                    {{ $submission->score }} / {{ $submission->max_score ?: $assignment->maxScore() }}
                                </span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1">Chưa chấm</span>
                            @endif
                        </td>
                        <td style="min-width: 280px;">
                            <form method="POST" action="{{ route('courses.assignments.submissions.grade', [$course, $assignment, $submission]) }}" class="d-flex flex-column gap-2">
                                @csrf
                                <div class="d-flex gap-2">
                                    <input name="score"
                                        type="number"
                                        min="0"
                                        max="{{ $assignment->maxScore() }}"
                                        step="0.01"
                                        value="{{ $submission->score }}"
                                        class="form-control form-control-sm"
                                        placeholder="Điểm (tối đa {{ $assignment->maxScore() }})"
                                        required
                                        style="max-width: 140px;">
                                    <button type="submit" class="btn btn-sm btn-primary px-3 fw-semibold">
                                        <i class="bi bi-floppy me-1"></i>Lưu
                                    </button>
                                </div>
                                <input name="feedback"
                                    value="{{ $submission->feedback }}"
                                    class="form-control form-control-sm"
                                    placeholder="Nhận xét của giáo viên (tùy chọn)...">
                            </form>
                        </td>
                        <td class="text-end pe-4">
                            @if($submission->file_path)
                                <a href="{{ route('courses.assignments.submissions.download', [$course, $assignment, $submission]) }}" class="btn btn-sm btn-outline-secondary" title="Tải bài làm học viên">
                                    <i class="bi bi-download me-1"></i>Tải file
                                </a>
                            @else
                                <span class="text-muted small fst-italic">Không có tệp</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-1 text-secondary"></i>
                            Chưa có học viên nào nộp bài tập này.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
