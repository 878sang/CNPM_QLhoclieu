@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <div class="small text-muted mb-1">
            <a href="{{ route('courses.assignments.index', $course) }}" class="text-decoration-none text-muted">
                <i class="bi bi-arrow-left me-1"></i>Danh sách bài tập – {{ $course->title }}
            </a>
        </div>
        <div class="d-flex align-items-center gap-2">
            <h1 class="h4 fw-bold mb-0 text-dark">{{ $assignment->title }}</h1>
            <span class="badge {{ $assignment->status === 'published' ? 'bg-success' : 'bg-warning text-dark' }}">
                {{ $assignment->status === 'published' ? 'Đã xuất bản' : 'Bản nháp' }}
            </span>
            <span class="badge bg-primary-subtle text-primary border">
                {{ $assignment->type === 'multiple_choice' ? 'Trắc nghiệm' : 'Tự luận' }}
            </span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('courses.assignments.edit', [$course, $assignment]) }}">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa bài tập
        </a>
        <a class="btn btn-primary" href="{{ route('courses.assignments.submissions.index', [$course, $assignment]) }}">
            <i class="bi bi-people me-1"></i>Xem bài nộp ({{ $submissions->count() }})
        </a>
    </div>
</div>

@if(session('status'))
    <x-alert tone="success" class="mb-3">{{ session('status') }}</x-alert>
@endif

{{-- Summary Info Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs">
            <div class="text-muted small mb-1"><i class="bi bi-list-ol me-1 text-primary"></i>Tổng số câu hỏi</div>
            <div class="h4 fw-bold mb-0 text-dark">{{ $assignment->question_count }} câu</div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs">
            <div class="text-muted small mb-1"><i class="bi bi-clock me-1 text-danger"></i>Thời gian làm bài</div>
            <div class="h4 fw-bold mb-0 text-dark">
                {{ $assignment->time_limit_minutes ? $assignment->time_limit_minutes.' phút' : 'Không giới hạn' }}
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs">
            <div class="text-muted small mb-1"><i class="bi bi-calendar-event me-1 text-warning"></i>Hạn nộp bài</div>
            <div class="h5 fw-bold mb-0 text-dark">
                {{ $assignment->deadline ? $assignment->deadline->format('H:i - d/m/Y') : 'Không giới hạn' }}
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs">
            <div class="text-muted small mb-1"><i class="bi bi-send-check me-1 text-success"></i>Đã nộp bài</div>
            <div class="h4 fw-bold mb-0 text-success">{{ $submissions->count() }} lượt</div>
        </div>
    </div>
</div>

{{-- Tabs: Đề bài, Đáp án & Danh sách nộp bài --}}
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="tab-questions-head" data-bs-toggle="tab" data-bs-target="#tab-questions-pane" type="button" role="tab">
            <i class="bi bi-file-earmark-text me-1 text-primary"></i>Đề bài &amp; Tệp đính kèm
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-answers-head" data-bs-toggle="tab" data-bs-target="#tab-answers-pane" type="button" role="tab">
            <i class="bi bi-check2-square me-1 text-success"></i>Bảng đáp án ({{ !empty($assignment->answer_key) ? count($assignment->answer_key) : 0 }} câu)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-subs-head" data-bs-toggle="tab" data-bs-target="#tab-subs-pane" type="button" role="tab">
            <i class="bi bi-people me-1 text-info"></i>Bài nộp gần đây ({{ $submissions->count() }})
        </button>
    </li>
</ul>

<div class="tab-content">
    {{-- TAB 1: ĐỀ BÀI --}}
    <div class="tab-pane fade show active" id="tab-questions-pane" role="tabpanel">
        <div class="card border rounded-3 p-4 bg-white shadow-2xs mb-4">
            @if($assignment->description)
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-2">Hướng dẫn / Mô tả:</h6>
                    <div class="p-3 bg-light rounded-3 border">{!! nl2br(e($assignment->description)) !!}</div>
                </div>
            @endif

            @if($assignment->file_path)
                @php($ext = strtolower(pathinfo($assignment->original_name ?? '', PATHINFO_EXTENSION)))
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                    <div class="fw-semibold text-dark">
                        <i class="bi bi-file-earmark-pdf text-danger me-1"></i>Tệp đề bài: <strong>{{ $assignment->original_name }}</strong>
                    </div>
                    <div class="d-flex gap-2">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('courses.assignments.preview_file', [$course, $assignment]) }}" target="_blank" rel="noopener">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Xem trực tiếp
                        </a>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('courses.assignments.download_file', [$course, $assignment]) }}">
                            <i class="bi bi-download me-1"></i>Tải về máy
                        </a>
                    </div>
                </div>

                @if($ext === 'pdf')
                    <div class="border rounded-3 overflow-hidden" style="height: 75vh; min-height: 500px;">
                        <iframe src="{{ route('courses.assignments.preview_file', [$course, $assignment]) }}" title="Đề bài" class="w-100 h-100 border-0"></iframe>
                    </div>
                @elseif(in_array($ext, ['jpg', 'jpeg', 'png', 'webp']))
                    <div class="text-center p-3 bg-light rounded border">
                        <img src="{{ route('courses.assignments.preview_file', [$course, $assignment]) }}" alt="Đề bài" class="img-fluid rounded" style="max-height: 75vh;">
                    </div>
                @endif
            @else
                <div class="p-4 text-center text-muted bg-light rounded border">
                    <i class="bi bi-file-earmark fs-2 d-block mb-1 text-secondary"></i>
                    Bài tập này chưa tải lên tệp đề bài đính kèm.
                </div>
            @endif
        </div>
    </div>

    {{-- TAB 2: ĐÁP ÁN --}}
    <div class="tab-pane fade" id="tab-answers-pane" role="tabpanel">
        <div class="card border rounded-3 p-4 bg-white shadow-2xs mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-check2-square text-success me-2"></i>Bảng đáp án chính thức</h5>
                @if($assignment->answer_file_path)
                    <div class="d-flex gap-2">
                        <a class="btn btn-sm btn-outline-success" href="{{ route('courses.assignments.preview_answer_file', [$course, $assignment]) }}" target="_blank">
                            <i class="bi bi-eye me-1"></i>Xem tệp lời giải
                        </a>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('courses.assignments.answer_file', [$course, $assignment]) }}">
                            <i class="bi bi-download me-1"></i>Tải về
                        </a>
                    </div>
                @endif
            </div>

            @if(!empty($assignment->answer_key))
                <div class="row g-2 g-md-3">
                    @foreach($assignment->answer_key as $question => $answer)
                        <div class="col-sm-6 col-md-4 col-xl-3">
                            <div class="p-2 px-3 border rounded-2 bg-light d-flex align-items-center justify-content-between">
                                <span class="small fw-bold text-secondary">Câu {{ $question }}:</span>
                                <span class="badge {{ $assignment->isMultipleChoice() ? 'bg-success' : 'bg-primary' }} fs-6">
                                    {{ $answer }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-4 text-center text-muted bg-light rounded border">
                    <i class="bi bi-card-checklist fs-2 d-block mb-1 text-secondary"></i>
                    Chưa có đáp án nào được nhập cho các câu hỏi của bài tập này.
                </div>
            @endif
        </div>
    </div>

    {{-- TAB 3: BÀI NỘP GẦN ĐÂY --}}
    <div class="tab-pane fade" id="tab-subs-pane" role="tabpanel">
        <div class="card border rounded-3 overflow-hidden bg-white shadow-2xs mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center py-3 px-4">
                <h5 class="fw-bold mb-0 text-dark">Danh sách bài nộp của học viên</h5>
                <a href="{{ route('courses.assignments.submissions.index', [$course, $assignment]) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Mở toàn bộ danh sách
                </a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Học viên</th>
                            <th>Thời gian nộp</th>
                            <th>Điểm số</th>
                            <th>Nhận xét</th>
                            <th class="text-end pe-4">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($submissions->take(10) as $submission)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold text-dark">{{ $submission->student?->name }}</div>
                                    <div class="small text-muted">{{ $submission->student?->email }}</div>
                                </td>
                                <td>{{ $submission->submitted_at?->format('H:i - d/m/Y') }}</td>
                                <td>
                                    @if($submission->isGraded())
                                        <span class="badge bg-success-subtle text-success border border-success fw-bold px-2 py-1 fs-6">
                                            {{ $submission->score }} / {{ $submission->max_score ?: $assignment->maxScore() }}
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning border border-warning">Chưa chấm</span>
                                    @endif
                                </td>
                                <td class="text-truncate" style="max-width: 250px;">
                                    {{ $submission->feedback ?: '—' }}
                                </td>
                                <td class="text-end pe-4">
                                    @if($submission->file_path)
                                        <a href="{{ route('courses.assignments.submissions.download', [$course, $assignment, $submission]) }}" class="btn btn-sm btn-outline-secondary me-1" title="Tải bài làm">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('courses.assignments.submissions.index', [$course, $assignment]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square me-1"></i>Chấm điểm
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-2 d-block mb-1 text-secondary"></i>
                                    Chưa có học viên nào nộp bài tập này.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
