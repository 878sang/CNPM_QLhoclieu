@extends('layouts.student')

@section('content')
<div class="mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <a href="{{ route('student.courses.learn', [$course, $assignment->lesson_id]) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Quay lại bài học
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="badge {{ $assignment->isMultipleChoice() ? 'bg-primary' : 'bg-info text-dark' }} px-3 py-2 fs-6">
                <i class="bi {{ $assignment->isMultipleChoice() ? 'bi-ui-radios-grid' : 'bi-input-cursor-text' }} me-1"></i>
                {{ $assignment->isMultipleChoice() ? 'Trắc nghiệm' : 'Tự luận / Nhập số' }}
            </span>
            @if($assignment->time_limit_minutes)
                <div class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 fs-6 d-flex align-items-center gap-1" id="timerBadge">
                    <i class="bi bi-stopwatch-fill"></i>
                    <span id="countdownDisplay">{{ sprintf('%02d:00', $assignment->time_limit_minutes) }}</span>
                </div>
            @endif
        </div>
    </div>
    <h1 class="h4 fw-bold text-dark mb-1">{{ $assignment->title }}</h1>
    @if($assignment->description)
        <p class="text-muted small mb-0">{{ $assignment->description }}</p>
    @endif
</div>

@if(session('error'))
    <x-alert tone="danger" class="mb-3">{{ session('error') }}</x-alert>
@endif
@if($errors->any())
    <div class="alert alert-danger border-0 shadow-2xs mb-3">
        <ul class="mb-0 ps-3 small">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('student.assignments.submit', [$course, $assignment]) }}" enctype="multipart/form-data" id="submitAssignmentForm">
    @csrf

    <div class="row g-4">
        {{-- CỘT TRÁI: Đề bài & tệp đính kèm --}}
        <div class="col-lg-7 col-xl-7">
            {{-- Đề bài file --}}
            @if($assignment->file_path)
                @php
                    $promptExtension = strtolower(pathinfo($assignment->original_name ?? '', PATHINFO_EXTENSION));
                @endphp
                <div class="card border rounded-3 shadow-2xs mb-3 overflow-hidden bg-white">
                    <div class="card-header bg-light d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 px-3">
                        <div class="fw-semibold text-truncate small">
                            <i class="bi bi-file-earmark-text text-primary me-1 fs-6"></i>Đề bài: <strong>{{ $assignment->original_name }}</strong>
                        </div>
                        <div class="d-flex gap-1">
                            <a class="btn btn-sm btn-outline-primary py-1 px-2" href="{{ route('student.assignments.preview_file_student', [$course, $assignment]) }}" target="_blank" rel="noopener" title="Mở tab mới">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Xem riêng
                            </a>
                            <a class="btn btn-sm btn-outline-secondary py-1 px-2" href="{{ route('student.assignments.download_file_student', [$course, $assignment]) }}" title="Tải về máy">
                                <i class="bi bi-download me-1"></i>Tải về
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-2 bg-dark d-flex justify-content-center align-items-center" style="min-height: 480px; max-height: 80vh; overflow: auto;">
                        @if($promptExtension === 'pdf')
                            <iframe src="{{ route('student.assignments.preview_file_student', [$course, $assignment]) }}" title="Đề bài" class="w-100 bg-white rounded border-0" style="height: 75vh; min-height: 520px;"></iframe>
                        @elseif(in_array($promptExtension, ['jpg', 'jpeg', 'png', 'webp']))
                            <img src="{{ route('student.assignments.preview_file_student', [$course, $assignment]) }}" alt="Đề bài" class="img-fluid rounded" style="max-height: 75vh; object-fit: contain;">
                        @else
                            <div class="text-center p-5 text-white">
                                <i class="bi bi-file-earmark-word fs-1 text-primary mb-2 d-block"></i>
                                <h6>Tệp đề bài: {{ $assignment->original_name }}</h6>
                                <p class="small text-white-50 mb-3">Tệp này cần được tải về máy để mở xem bằng phần mềm văn phòng chuyên dụng.</p>
                                <a class="btn btn-primary btn-sm px-3" href="{{ route('student.assignments.download_file_student', [$course, $assignment]) }}">
                                    <i class="bi bi-download me-1"></i>Tải tệp đề bài ngay
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Tùy chọn tải lên tệp bài làm thêm (nháp, bài giải tự luận) --}}
            <div class="card border rounded-3 shadow-2xs p-3 bg-white mb-3">
                <label class="form-label fw-bold text-dark small mb-1" for="submission-file">
                    <i class="bi bi-paperclip text-primary me-1"></i>Đính kèm tệp bài làm / tự luận (tùy chọn)
                </label>
                <input id="submission-file" type="file" name="file" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip">
                <div class="form-text small text-muted">
                    Hỗ trợ file PDF, Word, ảnh chụp bài giải tự luận (tối đa 20 MB). Bạn vẫn có thể điền bảng đáp án bên phải kết hợp với tải file.
                </div>
            </div>
        </div>

        {{-- CỘT PHẢI: Bảng đáp án (chuẩn theo mẫu ảnh của người dùng) --}}
        <div class="col-lg-5 col-xl-5">
            <div class="card border rounded-3 shadow-2xs bg-white sticky-top" style="top: 20px; z-index: 10;">
                {{-- Header đáp án theo ảnh mẫu --}}
                <div class="card-header bg-white border-bottom p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="fw-bold text-dark mb-0">Đáp án</h5>
                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1 small fw-semibold" id="answeredBadge">
                            Đã làm: <span id="answeredCount">0</span>/{{ $assignment->question_count }} câu
                        </span>
                    </div>

                    {{-- Thông tin số câu & thời gian làm bài theo ảnh mẫu --}}
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 small">
                        <span class="text-danger fw-semibold">
                            <i class="bi bi-mortarboard me-1"></i>Tổng số câu/điểm: <strong>{{ $assignment->question_count }} Câu / {{ $assignment->maxScore() }} điểm</strong>
                        </span>
                        <span class="text-danger fw-semibold">
                            <i class="bi bi-clock me-1"></i>Tổng thời gian: <strong>{{ $assignment->time_limit_minutes ? $assignment->time_limit_minutes.' phút' : 'Không giới hạn' }}</strong>
                        </span>
                    </div>

                    {{-- Progress bar hoàn thành --}}
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar bg-success progress-bar-striped" id="answeredProgress" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>

                {{-- Khung danh sách câu hỏi (Scrollable) --}}
                <div class="card-body p-3 overflow-auto" style="max-height: 62vh; min-height: 380px;">
                    <div class="row g-2 g-md-3">
                        @for($i = 1; $i <= $assignment->question_count; $i++)
                            @if($assignment->isMultipleChoice())
                                {{-- Giao diện trắc nghiệm ABCD (ảnh 2) --}}
                                <div class="col-6 mb-2">
                                    <div class="p-2 rounded-2 bg-light student-q-card">
                                        <div class="fw-bold text-dark small mb-2 ps-1">Câu {{ $i }}</div>
                                        <div class="d-flex align-items-center gap-1 justify-content-between px-1">
                                            @foreach(['A', 'B', 'C', 'D'] as $opt)
                                                <button type="button"
                                                    class="student-mc-btn"
                                                    onclick="selectStudentMC(this, {{ $i }}, '{{ $opt }}')"
                                                    data-opt="{{ $opt }}"
                                                    data-q="{{ $i }}"
                                                    id="btn_mc_{{ $i }}_{{ $opt }}">
                                                    {{ $opt }}
                                                </button>
                                            @endforeach
                                        </div>
                                        <input type="hidden" name="answers[{{ $i }}]" id="answer_input_{{ $i }}" value="">
                                    </div>
                                </div>
                            @else
                                {{-- Giao diện tự luận / nhập số (ảnh 1) --}}
                                <div class="col-6 mb-2">
                                    <div class="p-2 rounded-2 bg-light student-q-card">
                                        <label class="form-label fw-bold text-dark small mb-1 ps-1" for="answer_input_{{ $i }}">Câu {{ $i }}</label>
                                        <input type="text"
                                            name="answers[{{ $i }}]"
                                            id="answer_input_{{ $i }}"
                                            class="form-control form-control-sm bg-white student-text-ans"
                                            placeholder="Đáp án..."
                                            oninput="updateAnswerCount()">
                                    </div>
                                </div>
                            @endif
                        @endfor
                    </div>
                </div>

                {{-- Nút nộp bài footer --}}
                <div class="card-footer bg-light border-top p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAllStudentAnswers()">
                        <i class="bi bi-eraser me-1"></i>Xóa làm lại
                    </button>
                    <button type="button" class="btn btn-primary fw-bold px-4 shadow-sm" onclick="confirmSubmit()">
                        <i class="bi bi-send-fill me-1"></i>Nộp bài thi
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- MODAL XÁC NHẬN NỘP BÀI --}}
<div class="modal fade" id="submitConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-send-check me-2"></i>Xác nhận nộp bài
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="bi bi-question-circle text-primary display-4 mb-3 d-block"></i>
                <h5 class="fw-bold mb-2">Bạn có chắc chắn muốn nộp bài?</h5>
                <p class="text-muted mb-3" id="confirmModalText">
                    Bạn đã hoàn thành <strong>0/{{ $assignment->question_count }}</strong> câu hỏi.
                </p>
                <div class="alert alert-warning small text-start mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Sau khi bấm xác nhận nộp, bạn sẽ không thể chỉnh sửa lại bài làm này.
                </div>
            </div>
            <div class="modal-footer bg-light justify-content-center">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Kiểm tra lại</button>
                <button type="button" class="btn btn-primary px-4 fw-bold" onclick="document.getElementById('submitAssignmentForm').submit()">
                    <i class="bi bi-check2-circle me-1"></i>Đồng ý nộp bài
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Nút trắc nghiệm hình tròn theo mẫu ảnh 2 */
.student-mc-btn {
    width: 38px;
    height: 38px;
    border-radius: 50% !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.15s ease-in-out;
    cursor: pointer;
    background-color: #ffffff;
    border: 1.5px solid #ced4da;
    color: #495057;
    padding: 0;
}
.student-mc-btn:hover {
    border-color: #28a745;
    color: #28a745;
    background-color: #f8fff9;
}
.student-mc-btn.active {
    background-color: #28a745 !important;
    border-color: #28a745 !important;
    color: #ffffff !important;
    box-shadow: 0 2px 5px rgba(40, 167, 69, 0.4);
}
.student-q-card {
    border: 1px solid #e9ecef;
    transition: background-color 0.15s;
}
.student-q-card:hover {
    background-color: #fcfcfc;
    border-color: #dee2e6;
}
</style>

@push('scripts')
<script>
const totalQuestions = {{ $assignment->question_count }};

// Chọn đáp án trắc nghiệm (cho phép click lại để bỏ chọn)
function selectStudentMC(btn, qNum, opt) {
    const parent = btn.closest('.d-flex');
    const input = document.getElementById('answer_input_' + qNum);
    const wasActive = btn.classList.contains('active');

    parent.querySelectorAll('.student-mc-btn').forEach(b => b.classList.remove('active'));

    if (wasActive) {
        input.value = '';
    } else {
        btn.classList.add('active');
        input.value = opt;
    }

    updateAnswerCount();
}

// Cập nhật số câu đã hoàn thành và progress bar
function updateAnswerCount() {
    let answered = 0;
    for (let i = 1; i <= totalQuestions; i++) {
        const input = document.getElementById('answer_input_' + i);
        if (input && input.value && input.value.trim() !== '') {
            answered++;
        }
    }

    document.getElementById('answeredCount').textContent = answered;
    const percent = totalQuestions > 0 ? Math.round((answered / totalQuestions) * 100) : 0;
    document.getElementById('answeredProgress').style.width = percent + '%';
}

// Xóa tất cả câu đã làm để làm lại
function clearAllStudentAnswers() {
    if (!confirm('Bạn có chắc chắn muốn xóa toàn bộ câu trả lời đã làm?')) return;

    for (let i = 1; i <= totalQuestions; i++) {
        const input = document.getElementById('answer_input_' + i);
        if (input) input.value = '';
    }
    document.querySelectorAll('.student-mc-btn').forEach(b => b.classList.remove('active'));
    updateAnswerCount();
}

// Xác nhận trước khi nộp bài
function confirmSubmit() {
    let answered = 0;
    for (let i = 1; i <= totalQuestions; i++) {
        const input = document.getElementById('answer_input_' + i);
        if (input && input.value && input.value.trim() !== '') {
            answered++;
        }
    }

    const fileInput = document.getElementById('submission-file');
    const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

    let text = `Bạn đã hoàn thành <strong>${answered}/${totalQuestions}</strong> câu hỏi`;
    if (hasFile) {
        text += ` và đính kèm 1 tệp bài làm`;
    }
    text += `.`;

    if (answered < totalQuestions && !hasFile) {
        text += `<br><span class="text-danger fw-semibold">Chú ý: Vẫn còn ${totalQuestions - answered} câu chưa được trả lời!</span>`;
    }

    document.getElementById('confirmModalText').innerHTML = text;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('submitConfirmModal')).show();
}

// Đồng hồ đếm ngược (nếu có giới hạn thời gian)
@if($assignment->time_limit_minutes)
(function() {
    let remainingSeconds = {{ $assignment->time_limit_minutes * 60 }};
    const display = document.getElementById('countdownDisplay');
    const badge = document.getElementById('timerBadge');

    const timer = setInterval(() => {
        remainingSeconds--;
        if (remainingSeconds <= 0) {
            clearInterval(timer);
            alert('Đã hết thời gian làm bài! Hệ thống sẽ tự động nộp bài làm của bạn.');
            document.getElementById('submitAssignmentForm').submit();
            return;
        }

        const mins = Math.floor(remainingSeconds / 60);
        const secs = remainingSeconds % 60;
        display.textContent = (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;

        if (remainingSeconds < 300) { // Dưới 5 phút
            badge.className = 'badge bg-danger text-white px-3 py-2 fs-6 animate__animated animate__pulse animate__infinite';
        }
    }, 1000);
})();
@endif

// Khởi chạy đếm số câu ban đầu
updateAnswerCount();
</script>
@endpush
@endsection
