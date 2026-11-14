@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')

@section('content')
@php
    $assignment = $assignment ?? null;
    $editing = $assignment !== null;
    $selectedLessonId = $selectedLessonId ?? null;
    $selectedChapterId = old('chapter_id', $assignment?->lesson?->chapter_id ?? '');
    $initialType = old('type', $assignment->type ?? 'text');
    $initialCount = (int) old('question_count', $assignment->question_count ?? 10);
    $initialTime = old('time_limit_minutes', $assignment->time_limit_minutes ?? '');
@endphp

<div class="mb-4">
    <div class="small text-muted mb-1">
        <a href="{{ route('courses.assignments.index', $course) }}" class="text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i>Danh sách bài tập – {{ $course->title }}
        </a>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="h4 fw-bold mb-0">
            <i class="bi {{ $editing ? 'bi-pencil-square' : 'bi-plus-circle' }} text-primary me-2"></i>{{ $editing ? 'Chỉnh sửa bài tập' : 'Tạo bài tập mới' }}
        </h1>
        <span class="badge {{ $editing ? 'bg-info-subtle text-info border' : 'bg-primary-subtle text-primary border' }} fs-6">
            {{ $editing ? 'Đang cập nhật' : 'Tạo mới' }}
        </span>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger border-0 shadow-2xs mb-3">
    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Vui lòng kiểm tra lại các thông tin sau:</div>
    <ul class="mb-0 ps-3 small">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST"
    action="{{ $editing ? route('courses.assignments.update', [$course, $assignment]) : route('courses.assignments.store', $course) }}"
    enctype="multipart/form-data"
    id="assignmentForm">
    @csrf
    @if($editing) @method('PUT') @endif
    <input type="hidden" name="type" id="questionType" value="{{ $initialType }}">
    <input type="hidden" name="question_count" id="questionCount" value="{{ $initialCount }}">

    <div class="row g-4">
        {{-- CỘT TRÁI: Thông tin chung --}}
        <div class="col-lg-6 col-xl-5">
            <div class="card border shadow-2xs rounded-3 p-4 mb-3 bg-white">
                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                    <i class="bi bi-info-circle text-primary me-2"></i>Thông tin bài tập
                </h5>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Tiêu đề bài tập <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required
                        value="{{ old('title', $assignment->title ?? '') }}"
                        placeholder="VD: Bài tập chương 1 – Đơn điệu hàm số">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Mô tả / Hướng dẫn làm bài</label>
                    <textarea name="description" class="form-control" rows="3"
                        placeholder="Nhập hướng dẫn, yêu cầu hoặc lưu ý cho học viên...">{{ old('description', $assignment->description ?? '') }}</textarea>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label for="chapterSelect" class="form-label fw-semibold small text-muted">Chương học</label>
                        <select id="chapterSelect" name="chapter_id" class="form-select form-select-sm">
                            <option value="">— Tất cả chương —</option>
                            @foreach($chapters as $chapter)
                                <option value="{{ $chapter->id }}" {{ (string) $selectedChapterId === (string) $chapter->id ? 'selected' : '' }}>{{ $chapter->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="lessonSelect" class="form-label fw-semibold small text-muted">Gắn vào bài học</label>
                        <select id="lessonSelect" name="lesson_id" class="form-select form-select-sm">
                            <option value="">— Bài tập chung của khóa —</option>
                            @foreach($lessons as $lesson)
                            <option value="{{ $lesson->id }}" data-chapter-id="{{ $lesson->chapter_id }}"
                                {{ old('lesson_id', $assignment?->lesson_id ?? $selectedLessonId) == $lesson->id ? 'selected' : '' }}>
                                {{ $lesson->chapter?->title }} / {{ $lesson->title }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted">Trạng thái phát hành</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="draft" {{ old('status', $assignment->status ?? 'draft') === 'draft' ? 'selected' : '' }}>Bản nháp (chưa mở học viên)</option>
                            <option value="published" {{ old('status', $assignment->status ?? 'draft') === 'published' ? 'selected' : '' }}>Xuất bản (học viên thấy ngay)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted">Thời gian làm bài (phút)</label>
                        <input type="number" name="time_limit_minutes" id="timeLimitInput" class="form-control form-control-sm" min="1" max="600"
                            placeholder="Để trống = Không giới hạn"
                            value="{{ $initialTime }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Hạn nộp bài</label>
                    <input type="datetime-local" name="deadline" class="form-control form-control-sm"
                        value="{{ old('deadline', $assignment?->deadline?->format('Y-m-d\TH:i') ?? '') }}">
                    <div class="form-text small">Để trống nếu không giới hạn ngày giờ nộp.</div>
                </div>

                {{-- File đề bài --}}
                <div class="mb-3 pt-2 border-top">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-file-earmark-arrow-up me-1 text-primary"></i>Tải lên tệp đề bài (tùy chọn)
                    </label>
                    @if($editing && $assignment->file_path)
                    <div class="alert alert-light border d-flex align-items-center justify-content-between p-2 small mb-2">
                        <span class="text-truncate me-2"><i class="bi bi-paperclip me-1 text-primary"></i><strong>{{ $assignment->original_name }}</strong></span>
                        <a href="{{ route('courses.assignments.download_file', [$course, $assignment]) }}" class="btn btn-sm btn-outline-primary py-0">Tải về</a>
                    </div>
                    @endif
                    <input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <div class="form-text small">Hỗ trợ PDF, Word, Ảnh (tối đa 20 MB). Học viên có thể xem trực tuyến và tải đề khi làm bài.</div>
                </div>

                {{-- File đáp án / giải chi tiết --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-shield-check me-1 text-success"></i>Tệp lời giải / đáp án chi tiết (tùy chọn)
                    </label>
                    @if($editing && $assignment->answer_file_path)
                    <div class="alert alert-light border d-flex align-items-center justify-content-between p-2 small mb-2">
                        <span class="text-truncate me-2"><i class="bi bi-check2-circle me-1 text-success"></i><strong>{{ $assignment->answer_original_name }}</strong></span>
                        <a href="{{ route('courses.assignments.answer_file', [$course, $assignment]) }}" class="btn btn-sm btn-outline-secondary py-0">Tải về</a>
                    </div>
                    @endif
                    <input type="file" name="answer_file" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <div class="form-text small">File giải chi tiết chỉ giáo viên thấy (trừ khi bật xem đáp án bên dưới).</div>
                </div>

                <div class="form-check form-switch pt-2 border-top">
                    <input class="form-check-input" type="checkbox" role="switch" id="showAnswerToStudents" name="show_answer_to_students" value="1" {{ old('show_answer_to_students', $assignment->show_answer_to_students ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold small" for="showAnswerToStudents">
                        Cho phép học viên xem đáp án &amp; lời giải sau khi nộp bài
                    </label>
                </div>
            </div>
        </div>

        {{-- CỘT PHẢI: Bảng nhập đáp án (chuẩn theo mẫu người dùng) --}}
        <div class="col-lg-6 col-xl-7">
            <div class="card border shadow-2xs rounded-3 p-4 h-100 bg-white d-flex flex-column">
                {{-- Header đáp án đúng theo phong cách mẫu ảnh --}}
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h4 class="h5 fw-bold text-dark mb-0">Đáp án</h4>
                        {{-- Nút chuyển đổi loại câu hỏi --}}
                        <div class="btn-group btn-group-sm" role="group" aria-label="Loại câu hỏi">
                            <button type="button" class="btn btn-outline-primary" id="btnTypeText" onclick="switchQuestionType('text')">
                                <i class="bi bi-input-cursor-text me-1"></i>Tự luận / Nhập số
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="btnTypeMC" onclick="switchQuestionType('multiple_choice')">
                                <i class="bi bi-check-circle me-1"></i>Trắc nghiệm (A/B/C/D)
                            </button>
                        </div>
                    </div>

                    {{-- Thông tin số câu & thời gian (theo mẫu ảnh) --}}
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 small text-muted">
                        <div>
                            <span class="text-danger fw-semibold">
                                <i class="bi bi-mortarboard me-1"></i>Tổng số câu/điểm:
                                <strong id="totalQNum">0</strong> Câu / <strong id="totalQNum2">0</strong> điểm
                            </span>
                        </div>
                        <div>
                            <span class="text-danger fw-semibold">
                                <i class="bi bi-clock me-1"></i>Tổng thời gian làm bài:
                                <strong id="timeLimitLabel">{{ $initialTime ? $initialTime.' phút' : 'Không giới hạn' }}</strong>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Toolbar thêm câu hỏi: + 1 câu, + 5 câu, + 10 câu, + Thêm tiếp số câu... --}}
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 p-2 bg-light rounded-2 border">
                    <div class="d-flex align-items-center gap-1 flex-wrap">
                        <span class="small fw-semibold text-secondary me-1"><i class="bi bi-plus-lg me-1"></i>Thêm câu hỏi:</span>
                        <button type="button" class="btn btn-sm btn-white border shadow-2xs py-1 px-2 fw-semibold" onclick="addQuestions(1)">
                            +1 câu
                        </button>
                        <button type="button" class="btn btn-sm btn-white border shadow-2xs py-1 px-2 fw-semibold" onclick="addQuestions(5)">
                            +5 câu
                        </button>
                        <button type="button" class="btn btn-sm btn-white border shadow-2xs py-1 px-2 fw-semibold" onclick="addQuestions(10)">
                            +10 câu
                        </button>
                        <button type="button" class="btn btn-sm btn-primary py-1 px-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#questionModal">
                            <i class="bi bi-plus-circle me-1"></i>Thêm tiếp số câu...
                        </button>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" onclick="clearAllQuestions()" title="Xóa tất cả câu hỏi">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>

                {{-- Container lưới câu hỏi (Scrollable) --}}
                <div id="answerKeyContainer" class="flex-grow-1 overflow-auto pe-1" style="max-height: 580px; min-height: 320px;">
                    {{-- Rendered dynamically by JavaScript --}}
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom submit buttons --}}
    <div class="card border rounded-3 p-3 bg-white shadow-2xs mt-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="text-muted small">
                <i class="bi bi-check-circle-fill text-success me-1"></i>
                Học viên sẽ thấy giao diện làm bài nhập đáp án tương ứng đúng theo định dạng và số câu bạn thiết lập ở trên.
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('courses.assignments.index', $course) }}" class="btn btn-outline-secondary px-3">
                    Hủy bỏ
                </a>
                <button type="submit" class="btn btn-primary fw-semibold px-4">
                    <i class="bi bi-floppy me-1"></i>{{ $editing ? 'Lưu cập nhật bài tập' : 'Hoàn tất & Tạo bài tập' }}
                </button>
            </div>
        </div>
    </div>
</form>

{{-- MODAL THÊM TIẾP CÂU HỎI --}}
<div class="modal fade" id="questionModal" tabindex="-1" aria-labelledby="questionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="questionModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Thêm câu hỏi vào bài tập
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label for="questionModalAddCount" class="form-label fw-semibold">Số câu hỏi muốn thêm tiếp: <span class="text-danger">*</span></label>
                    <input type="number" id="questionModalAddCount" class="form-control form-control-lg text-center fw-bold" min="1" max="100" value="5" placeholder="VD: 5, 10, 20">
                    <div class="form-text mt-2 text-muted">
                        <i class="bi bi-info-circle-fill text-primary me-1"></i>
                        Hệ thống sẽ <strong>giữ nguyên toàn bộ câu hỏi và đáp án đã có</strong>, sau đó thêm tiếp các câu mới vào cuối danh sách bài tập.
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" id="confirmQuestionsBtn" class="btn btn-primary px-4 fw-semibold">
                    <i class="bi bi-plus-lg me-1"></i>Thêm tiếp ngay
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Nút trắc nghiệm hình tròn theo mẫu ảnh 2 */
.mc-opt-btn {
    width: 36px;
    height: 36px;
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
.mc-opt-btn:hover {
    border-color: #28a745;
    color: #28a745;
    background-color: #f8fff9;
}
.mc-opt-btn.active {
    background-color: #28a745 !important;
    border-color: #28a745 !important;
    color: #ffffff !important;
    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.35);
}
.question-item-card {
    transition: background-color 0.15s;
    border: 1px solid #e9ecef;
}
.question-item-card:hover {
    background-color: #fdfdfd;
    border-color: #dee2e6;
}
</style>

@push('scripts')
<script>
// Server initial state
const existingAnswerKey = @json($assignment->answer_key ?? null);
let answerKeyOverride = null;

// Lấy toàn bộ đáp án hiện tại đang nhập trên form
function collectAnswerKey() {
    const values = {};
    document.querySelectorAll('[name^="answer_key["]').forEach(input => {
        const match = input.name.match(/answer_key\[(\d+)\]/);
        if (match && input.value !== null && input.value !== undefined && input.value !== '') {
            values[match[1]] = input.value;
        }
    });
    return values;
}

// Chuyển đổi định dạng câu hỏi (Tự luận số / Trắc nghiệm ABCD)
function switchQuestionType(type) {
    const current = collectAnswerKey();
    answerKeyOverride = current;
    document.getElementById('questionType').value = type;
    updateTypeButtons(type);
    renderAnswerKey();
}

function updateTypeButtons(type) {
    const btnText = document.getElementById('btnTypeText');
    const btnMC = document.getElementById('btnTypeMC');
    if (type === 'multiple_choice') {
        btnMC.className = 'btn btn-primary active fw-semibold';
        btnText.className = 'btn btn-outline-primary';
    } else {
        btnText.className = 'btn btn-primary active fw-semibold';
        btnMC.className = 'btn btn-outline-primary';
    }
}

// Thêm tiếp N câu hỏi vào danh sách (không thay thế câu cũ)
function addQuestions(countToAdd) {
    const countInput = document.getElementById('questionCount');
    const currentCount = parseInt(countInput.value, 10) || 0;
    const currentAnswers = collectAnswerKey();

    const newCount = currentCount + countToAdd;
    if (newCount > 200) {
        alert('Số lượng câu hỏi tối đa cho một bài tập là 200 câu.');
        return;
    }

    countInput.value = newCount;
    answerKeyOverride = currentAnswers; // Giữ nguyên toàn bộ đáp án cũ
    renderAnswerKey();

    // Cuộn xuống cuối để thấy câu mới thêm
    const container = document.getElementById('answerKeyContainer');
    setTimeout(() => {
        container.scrollTop = container.scrollHeight;
    }, 50);
}

// Xóa 1 câu hỏi cụ thể
function removeQuestion(questionNumber) {
    const countInput = document.getElementById('questionCount');
    const count = parseInt(countInput.value, 10) || 0;
    if (count <= 1) {
        if (!confirm('Bạn có muốn xóa câu hỏi cuối cùng không?')) return;
        countInput.value = 0;
        answerKeyOverride = {};
        renderAnswerKey();
        return;
    }

    const current = collectAnswerKey();
    const updated = {};
    for (let oldNumber = 1; oldNumber <= count; oldNumber++) {
        if (oldNumber === questionNumber) continue;
        const newNumber = oldNumber > questionNumber ? oldNumber - 1 : oldNumber;
        if (current[oldNumber] !== undefined) {
            updated[newNumber] = current[oldNumber];
        }
    }

    countInput.value = count - 1;
    answerKeyOverride = updated;
    renderAnswerKey();
}

// Xóa tất cả câu hỏi
function clearAllQuestions() {
    if (!confirm('Bạn có chắc chắn muốn xóa toàn bộ câu hỏi và nhập lại từ đầu?')) return;
    document.getElementById('questionCount').value = 0;
    answerKeyOverride = {};
    renderAnswerKey();
}

// Render toàn bộ lưới đáp án theo mẫu ảnh
function renderAnswerKey() {
    const type = document.getElementById('questionType').value;
    const count = parseInt(document.getElementById('questionCount').value, 10) || 0;
    const container = document.getElementById('answerKeyContainer');
    const currentAnswerKey = answerKeyOverride ?? collectAnswerKey();
    answerKeyOverride = null;

    document.getElementById('totalQNum').textContent = count;
    document.getElementById('totalQNum2').textContent = count;
    updateTypeButtons(type);

    if (count < 1) {
        container.innerHTML = `
            <div class="text-center text-muted border border-dashed rounded-3 p-5 my-2">
                <i class="bi bi-card-checklist fs-1 d-block mb-2 text-secondary"></i>
                <h6 class="fw-bold text-dark mb-1">Chưa có câu hỏi nào trong bài tập</h6>
                <p class="small text-muted mb-3">Bấm vào các nút "+1 câu", "+5 câu", "+10 câu" hoặc "Thêm tiếp số câu..." ở phía trên để bắt đầu nhập đáp án.</p>
                <button type="button" class="btn btn-sm btn-primary" onclick="addQuestions(10)">
                    <i class="bi bi-plus-circle me-1"></i>Thêm nhanh 10 câu đầu tiên
                </button>
            </div>
        `;
        return;
    }

    let html = '<div class="row g-2 g-md-3">';

    for (let i = 1; i <= count; i++) {
        const existing = currentAnswerKey[i] ?? (existingAnswerKey ? (existingAnswerKey[i] ?? existingAnswerKey[String(i)] ?? '') : '');

        if (type === 'multiple_choice') {
            const opts = ['A', 'B', 'C', 'D'];
            html += `
                <div class="col-sm-6 mb-2">
                    <div class="p-2 px-3 rounded-2 bg-light question-item-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-bold text-dark small">Câu ${i}</span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" onclick="removeQuestion(${i})" title="Xóa câu ${i}">
                                <i class="bi bi-x-lg small"></i>
                            </button>
                        </div>
                        <div class="d-flex align-items-center gap-2">`;
            opts.forEach(opt => {
                const checked = existing.toUpperCase() === opt;
                html += `
                    <button type="button"
                        class="mc-opt-btn ${checked ? 'active' : ''}"
                        onclick="selectMC(this, ${i}, '${opt}')"
                        data-opt="${opt}">
                        ${opt}
                    </button>`;
            });
            html += `
                        </div>
                        <input type="hidden" name="answer_key[${i}]" id="mc_${i}" value="${existing}">
                    </div>
                </div>`;
        } else {
            // Tự luận / Nhập số theo mẫu Screenshot 1
            html += `
                <div class="col-sm-6 mb-2">
                    <div class="p-2 px-3 rounded-2 bg-light question-item-card">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label fw-bold text-dark small mb-0" for="ans_input_${i}">Câu ${i}</label>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" onclick="removeQuestion(${i})" title="Xóa câu ${i}">
                                <i class="bi bi-x-lg small"></i>
                            </button>
                        </div>
                        <input type="text"
                            name="answer_key[${i}]"
                            id="ans_input_${i}"
                            class="form-control form-control-sm bg-white"
                            placeholder="Nhập đáp án câu ${i}..."
                            value="${existing}">
                    </div>
                </div>`;
        }
    }
    html += '</div>';
    container.innerHTML = html;
}

// Xử lý chọn đáp án trắc nghiệm (cho phép click lại để bỏ chọn nếu muốn)
function selectMC(btn, qNum, opt) {
    const parent = btn.closest('.d-flex');
    const input = document.getElementById('mc_' + qNum);
    const wasActive = btn.classList.contains('active');

    parent.querySelectorAll('.mc-opt-btn').forEach(b => b.classList.remove('active'));

    if (wasActive) {
        input.value = '';
    } else {
        btn.classList.add('active');
        input.value = opt;
    }
}

// Modal Thêm câu hỏi: Thêm tiếp vào cuối danh sách
document.getElementById('confirmQuestionsBtn').addEventListener('click', () => {
    const addCount = parseInt(document.getElementById('questionModalAddCount').value, 10);
    if (!Number.isInteger(addCount) || addCount < 1 || addCount > 100) {
        document.getElementById('questionModalAddCount').focus();
        return;
    }

    addQuestions(addCount);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('questionModal')).hide();
});

// Đồng bộ nhãn thời gian làm bài
const timeLimitInput = document.getElementById('timeLimitInput');
if (timeLimitInput) {
    timeLimitInput.addEventListener('input', () => {
        const val = timeLimitInput.value.trim();
        document.getElementById('timeLimitLabel').textContent = val ? val + ' phút' : 'Không giới hạn';
    });
}

// Lọc động bài học theo chương
const chapterSelect = document.getElementById('chapterSelect');
const lessonSelect = document.getElementById('lessonSelect');
if (chapterSelect && lessonSelect) {
    const filterLessons = () => {
        const chapterId = chapterSelect.value;
        Array.from(lessonSelect.options).forEach(option => {
            option.hidden = option.value !== '' && chapterId !== '' && option.dataset.chapterId !== chapterId;
        });
        if (lessonSelect.selectedOptions[0]?.hidden) {
            lessonSelect.value = '';
        }
    };
    chapterSelect.addEventListener('change', filterLessons);
    filterLessons();
}

// Khởi chạy ban đầu
renderAnswerKey();
</script>
@endpush
@endsection
