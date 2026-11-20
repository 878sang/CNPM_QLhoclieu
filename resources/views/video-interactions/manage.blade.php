@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')

@section('content')
<x-page-header
    eyebrow="TƯƠNG TÁC TRÊN VIDEO"
    :title="'Câu hỏi tương tác: ' . $document->title"
    description="Thêm, chỉnh sửa một hoặc nhiều câu hỏi liên tiếp (trắc nghiệm 1 đáp án đúng, đúng/sai, trả lời ngắn) tại các mốc thời gian trên video."
>
    <x-slot:actions>
        <a href="{{ route('courses.show', ['course' => $course, 'lesson_id' => $document->lesson_id, 'tab' => 'content']) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Quay lại bài giảng
        </a>
    </x-slot:actions>
</x-page-header>

@if (session('status'))
    <x-alert type="success" class="mb-3">{{ session('status') }}</x-alert>
@endif
@if ($errors->any())
    <x-alert type="danger" class="mb-3">{{ $errors->first() }}</x-alert>
@endif

<!-- THỐNG KÊ NHANH -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs h-100">
            <div class="small text-muted">Số điểm dừng / Tổng câu hỏi</div>
            <div class="fs-3 fw-bold text-primary">
                {{ $checkpointCount }} <span class="fs-6 fw-normal text-muted">điểm dừng</span> · {{ $interactions->count() }} <span class="fs-6 fw-normal text-muted">câu hỏi</span>
            </div>
            <div class="small text-muted">{{ $course->title }} @if($document->lesson) · {{ $document->lesson->title }} @endif</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs h-100">
            <div class="small text-muted">Tổng lượt học viên trả lời</div>
            <div class="fs-3 fw-bold text-dark">{{ $totalAttempts }}</div>
            <div class="small text-muted">Tính trên toàn bộ câu hỏi của video này</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs h-100">
            <div class="small text-muted">Tỷ lệ trả lời đúng</div>
            <div class="fs-3 fw-bold text-success">{{ $correctRate }}%</div>
            <div class="small text-muted">Đánh giá mức độ nắm bài khi xem video</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- CỘT TRÁI: TRÌNH PHÁT VIDEO & FORM THÊM NHIỀU CÂU HỎI -->
    <div class="col-lg-6">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0"><i class="bi bi-play-btn-fill text-danger me-1"></i>Xem trước video & dấu mốc tương tác</h2>
                <span class="badge bg-dark font-monospace" id="studioCurrentTime">00:00</span>
            </div>

            <div class="rounded overflow-hidden bg-black mb-2">
                @if($document->file_path)
                    <video id="studioVideoPlayer" controls class="w-100" style="max-height: 320px; background: #000;">
                        <source src="{{ route('courses.documents.show', [$course, $document]) }}" type="{{ $document->mime_type ?: 'video/mp4' }}">
                    </video>
                @elseif($document->url)
                    <div class="ratio ratio-16x9">
                        <iframe
                            id="studioYouTubePlayer"
                            src="{{ $document->isYouTube() ? $document->embed_url.'?enablejsapi=1&origin='.urlencode(request()->getSchemeAndHttpHost()).'&rel=0' : $document->embed_url }}"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </div>
                @endif
            </div>

            <!-- THANH TIMELINE ĐÁNH DẤU VỊ TRÍ CÂU HỎI TRÊN VIDEO -->
            <div class="p-2 px-3 bg-light rounded border mb-2">
                <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                    <span><i class="bi bi-geo-alt-fill text-danger me-1"></i>Vị trí các điểm tương tác trên thanh video</span>
                    <span class="font-monospace" id="studioDurationLabel">00:00</span>
                </div>
                <div class="position-relative rounded bg-secondary bg-opacity-25" style="height: 12px;" id="studioTimelineTrack">
                    <div class="bg-primary rounded h-100" id="studioTimelineFill" style="width: 0%;"></div>
                    <div id="studioTimelineMarkers" class="position-absolute top-0 start-0 w-100 h-100"></div>
                </div>
            </div>

            <div class="small text-muted">
                <i class="bi bi-lightbulb text-warning me-1"></i>Mẹo: Tua video đến đúng đoạn cần hỏi, sau đó bấm nút <strong>Lấy mốc</strong> ngay trên từng câu hỏi bên dưới. Bạn có thể tạo liên tiếp nhiều câu hỏi cùng lúc rồi bấm Lưu một lần.
            </div>
        </div>

        <!-- FORM THÊM 1 HOẶC NHIỀU CÂU HỎI LIÊN TIẾP -->
        <div class="card border rounded-3 p-3 p-md-4 bg-white shadow-2xs">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h6 fw-bold mb-0 text-uppercase">
                    <i class="bi bi-plus-circle text-primary me-1"></i>Thêm câu hỏi tương tác
                </h2>
                <span class="badge bg-primary-subtle text-primary border border-primary" id="batchCountBadge">1 câu hỏi</span>
            </div>

            <form method="POST" action="{{ route('courses.video-interactions.store', [$course, $document]) }}" id="batchCreateForm">
                @csrf
                <div id="questionCardsContainer" class="d-flex flex-column gap-3 mb-3"></div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1" id="addSameTimestampBtn">
                        <i class="bi bi-plus-lg me-1"></i>Thêm câu tiếp (cùng mốc)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1" id="addNewTimestampBtn">
                        <i class="bi bi-stopwatch me-1"></i>Thêm câu ở mốc mới
                    </button>
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-semibold" id="submitBatchBtn">
                    <i class="bi bi-save me-1"></i>Lưu tất cả câu hỏi vào video
                </button>
            </form>
        </div>
    </div>

    <!-- CỘT PHẢI: DANH SÁCH CÂU HỎI ĐÃ TẠO (CÓ SỬA / XÓA) -->
    <div class="col-lg-6">
        <div class="card border rounded-3 p-3 p-md-4 bg-white shadow-2xs">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold mb-0 text-uppercase">
                    <i class="bi bi-list-check text-success me-1"></i>Danh sách câu hỏi ({{ $interactions->count() }})
                </h2>
                <span class="small text-muted">{{ $checkpointCount }} mốc thời gian</span>
            </div>

            @forelse($interactions as $idx => $item)
                @php
                    $rate = $item->total_responses_count > 0
                        ? (int) round($item->correct_responses_count * 100 / $item->total_responses_count)
                        : null;
                @endphp
                <div class="border rounded-3 p-3 mb-3 bg-light">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge bg-secondary">#{{ $idx + 1 }}</span>
                            <button type="button" class="btn btn-sm btn-dark font-monospace py-0 px-2 seek-studio-btn" data-seconds="{{ $item->timestamp_seconds }}" title="Tua video tới mốc này">
                                <i class="bi bi-play-fill"></i> {{ $item->formattedTimestamp() }}
                            </button>
                            <span class="badge {{ $item->typeBadgeClass() }}">{{ $item->typeLabel() }}</span>
                            @if($item->is_required)
                                <span class="badge bg-danger-subtle text-danger border border-danger">Bắt buộc</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border">Tùy chọn</span>
                            @endif
                        </div>
                        <div class="d-flex gap-1">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary py-0 px-2 edit-interaction-btn"
                                data-id="{{ $item->id }}"
                                title="Sửa câu hỏi này"
                            >
                                <i class="bi bi-pencil-square me-1"></i>Sửa
                            </button>
                            <form method="POST" action="{{ route('courses.video-interactions.destroy', [$course, $document, $item]) }}" onsubmit="return confirm('Xóa câu hỏi tương tác tại mốc {{ $item->formattedTimestamp() }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Xóa câu hỏi">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="fw-semibold text-dark mb-2">{{ $item->question }}</div>

                    @if($item->question_type === 'multiple_choice' && is_array($item->options))
                        <div class="row g-1 mb-2 small">
                            @foreach($item->options as $key => $optText)
                                <div class="col-sm-6">
                                    <div class="p-1 px-2 rounded border {{ strtoupper((string) $item->correct_answer) === strtoupper((string) $key) ? 'bg-success-subtle border-success fw-semibold text-success-emphasis' : 'bg-white text-muted' }}">
                                        @if(strtoupper((string) $item->correct_answer) === strtoupper((string) $key))
                                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                                        @endif
                                        <strong>{{ $key }}.</strong> {{ $optText }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="small mb-2">
                            <span class="text-muted">Đáp án đúng:</span>
                            <span class="badge bg-success ms-1">{{ $item->correctAnswerLabel() }}</span>
                        </div>
                    @endif

                    @if($item->explanation)
                        <div class="small text-muted bg-white p-2 rounded border mb-2">
                            <i class="bi bi-lightbulb text-warning me-1"></i><strong>Giải thích:</strong> {{ $item->explanation }}
                        </div>
                    @endif

                    <div class="d-flex flex-wrap justify-content-between align-items-center small text-muted pt-2 border-top">
                        <span>
                            <i class="bi bi-people me-1"></i>{{ $item->total_responses_count }} lượt trả lời
                            @if($rate !== null)
                                · <span class="text-success fw-semibold">{{ $rate }}% đúng</span>
                            @endif
                        </span>
                        @if($item->responses->isNotEmpty())
                            <span>Gần nhất: {{ $item->responses->first()->user?->name }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <x-empty-state
                    icon="patch-question"
                    title="Chưa có câu hỏi tương tác nào"
                    message="Hãy dùng form bên trái để thêm một hoặc nhiều câu hỏi liên tiếp tại các mốc thời gian trên video."
                />
            @endforelse
        </div>
    </div>
</div>

<!-- MODAL SỬA CÂU HỎI TƯƠNG TÁC -->
<div class="modal fade" id="editInteractionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title h6 fw-bold"><i class="bi bi-pencil-square me-1"></i>Chỉnh sửa câu hỏi tương tác trên video</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editInteractionForm">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-5">
                            <label class="form-label small fw-semibold" for="edit_timestamp_input">Mốc thời gian (mm:ss hoặc giây)</label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="timestamp_input" id="edit_timestamp_input" class="form-control font-monospace" required>
                                <button type="button" class="btn btn-outline-primary" id="editCaptureTimeBtn" title="Lấy thời gian hiện tại từ video">
                                    <i class="bi bi-stopwatch"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label small fw-semibold" for="edit_question_type">Loại câu hỏi</label>
                            <select name="question_type" id="edit_question_type" class="form-select form-select-sm">
                                <option value="multiple_choice">Trắc nghiệm (1 đáp án đúng)</option>
                                <option value="true_false">Đúng / Sai</option>
                                <option value="short_answer">Trả lời ngắn</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="edit_question">Nội dung câu hỏi</label>
                        <textarea name="question" id="edit_question" rows="2" class="form-control" required></textarea>
                    </div>

                    <!-- Sửa Trắc nghiệm (1 đáp án đúng) -->
                    <div id="editMcGroup" class="mb-3 p-3 bg-light rounded border">
                        <div class="small fw-semibold text-muted mb-2">
                            Các phương án trả lời — tích chọn <strong>1 đáp án đúng duy nhất</strong>:
                        </div>
                        <div class="row g-2">
                            @foreach(['A', 'B', 'C', 'D'] as $letter)
                                @php $lower = strtolower($letter); @endphp
                                <div class="col-sm-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white">
                                            <input class="form-check-input mt-0 edit-mc-radio" type="radio" name="edit_mc_correct" value="{{ $letter }}" title="Chọn {{ $letter }} là đáp án đúng">
                                        </span>
                                        <span class="input-group-text fw-bold">{{ $letter }}</span>
                                        <input type="text" name="option_{{ $lower }}" id="edit_option_{{ $lower }}" class="form-control" placeholder="Phương án {{ $letter }}">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Sửa Đúng / Sai -->
                    <div id="editTfGroup" class="mb-3 p-3 bg-light rounded border d-none">
                        <label class="form-label small fw-semibold mb-2">Chọn đáp án đúng</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="edit_tf_correct" id="editTfTrue" value="true">
                                <label class="form-check-label fw-semibold text-success" for="editTfTrue">Đúng</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="edit_tf_correct" id="editTfFalse" value="false">
                                <label class="form-check-label fw-semibold text-danger" for="editTfFalse">Sai</label>
                            </div>
                        </div>
                    </div>

                    <!-- Sửa Trả lời ngắn -->
                    <div id="editShortGroup" class="mb-3 p-3 bg-light rounded border d-none">
                        <label class="form-label small fw-semibold mb-1" for="edit_short_correct">Đáp án đúng (ngăn cách nhiều đáp án bằng dấu |)</label>
                        <input type="text" id="edit_short_correct" class="form-control form-control-sm">
                    </div>

                    <input type="hidden" name="correct_answer" id="edit_correct_answer">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="edit_explanation">Giải thích đáp án</label>
                        <textarea name="explanation" id="edit_explanation" rows="2" class="form-control form-control-sm"></textarea>
                    </div>

                    <div class="form-check">
                        <input type="hidden" name="is_required" value="0">
                        <input class="form-check-input" type="checkbox" name="is_required" value="1" id="edit_is_required">
                        <label class="form-check-label small" for="edit_is_required">
                            Bắt buộc học viên trả lời trước khi tiếp tục xem video
                        </label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i>Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@php
    $studioMarkersPayload = $interactions->map(fn ($vi) => [
        'id' => $vi->id,
        'seconds' => (int) $vi->timestamp_seconds,
        'formatted' => $vi->formattedTimestamp(),
        'question' => \Illuminate\Support\Str::limit($vi->question, 60),
    ])->values();

    $editItemsMap = $interactions->mapWithKeys(fn ($vi) => [
        $vi->id => [
            'id' => $vi->id,
            'update_url' => route('courses.video-interactions.update', [$course, $document, $vi]),
            'timestamp_input' => $vi->formattedTimestamp(),
            'question_type' => $vi->question_type,
            'question' => $vi->question,
            'options' => $vi->options ?? [],
            'correct_answer' => $vi->correct_answer,
            'explanation' => $vi->explanation ?? '',
            'is_required' => (bool) $vi->is_required,
        ],
    ]);
@endphp

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const videoEl = document.getElementById('studioVideoPlayer');
    const ytIframe = document.getElementById('studioYouTubePlayer');
    const timeBadge = document.getElementById('studioCurrentTime');
    const durationLabel = document.getElementById('studioDurationLabel');
    const timelineFill = document.getElementById('studioTimelineFill');
    const markersContainer = document.getElementById('studioTimelineMarkers');
    const studioMarkers = @json($studioMarkersPayload);
    let ytPlayer = null;
    let videoDuration = 0;

    function formatMMSS(sec) {
        sec = Math.max(0, Math.floor(sec || 0));
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function getCurrentSeconds() {
        if (videoEl) return Math.floor(videoEl.currentTime || 0);
        if (ytPlayer && typeof ytPlayer.getCurrentTime === 'function') {
            try { return Math.floor(ytPlayer.getCurrentTime() || 0); } catch (e) { return 0; }
        }
        return 0;
    }

    function pausePreviewVideo() {
        if (videoEl) videoEl.pause();
        if (ytPlayer && typeof ytPlayer.pauseVideo === 'function') {
            try { ytPlayer.pauseVideo(); } catch (e) {}
        }
    }

    function seekPreviewVideo(sec) {
        if (videoEl) {
            videoEl.currentTime = sec;
            videoEl.play().catch(() => {});
        } else if (ytPlayer && typeof ytPlayer.seekTo === 'function') {
            ytPlayer.seekTo(sec, true);
            ytPlayer.playVideo();
        }
    }

    function renderStudioTimelineMarkers(duration) {
        if (!markersContainer || !duration || duration <= 0) return;
        videoDuration = duration;
        if (durationLabel) durationLabel.textContent = formatMMSS(duration);
        markersContainer.innerHTML = '';

        // Nhóm theo mốc thời gian
        const grouped = {};
        studioMarkers.forEach(m => {
            if (!grouped[m.seconds]) grouped[m.seconds] = [];
            grouped[m.seconds].push(m);
        });

        Object.entries(grouped).forEach(([secStr, list]) => {
            const sec = Number(secStr);
            const pct = Math.min(99, Math.max(1, (sec / duration) * 100));
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'position-absolute top-50 translate-middle border border-white rounded-circle bg-danger p-0 shadow-sm';
            dot.style.left = pct + '%';
            dot.style.width = '14px';
            dot.style.height = '14px';
            dot.style.cursor = 'pointer';
            dot.title = list.length > 1
                ? `Mốc ${list[0].formatted}: ${list.length} câu hỏi tương tác`
                : `Mốc ${list[0].formatted}: ${list[0].question}`;
            dot.addEventListener('click', () => seekPreviewVideo(sec));
            markersContainer.appendChild(dot);
        });
    }

    if (videoEl) {
        videoEl.addEventListener('loadedmetadata', () => renderStudioTimelineMarkers(videoEl.duration));
        if (videoEl.readyState >= 1) renderStudioTimelineMarkers(videoEl.duration);
        videoEl.addEventListener('timeupdate', () => {
            if (timeBadge) timeBadge.textContent = formatMMSS(videoEl.currentTime);
            if (timelineFill && videoEl.duration > 0) {
                timelineFill.style.width = Math.min(100, (videoEl.currentTime / videoEl.duration) * 100) + '%';
            }
        });
    }

    @if($document->isYouTube())
    function initStudioYT() {
        if (!ytIframe) return;
        ytPlayer = new YT.Player('studioYouTubePlayer', {
            events: {
                onReady: function () {
                    const dur = ytPlayer.getDuration ? ytPlayer.getDuration() : 0;
                    if (dur > 0) renderStudioTimelineMarkers(dur);
                    setInterval(() => {
                        if (ytPlayer && typeof ytPlayer.getCurrentTime === 'function') {
                            const cur = ytPlayer.getCurrentTime() || 0;
                            const d = ytPlayer.getDuration ? ytPlayer.getDuration() : videoDuration;
                            if (d > 0 && d !== videoDuration) renderStudioTimelineMarkers(d);
                            if (timeBadge) timeBadge.textContent = formatMMSS(cur);
                            if (timelineFill && d > 0) {
                                timelineFill.style.width = Math.min(100, (cur / d) * 100) + '%';
                            }
                        }
                    }, 500);
                }
            }
        });
    }
    if (window.YT && window.YT.Player) {
        initStudioYT();
    } else {
        window.onYouTubeIframeAPIReady = initStudioYT;
        const s = document.createElement('script');
        s.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(s);
    }
    @endif

    document.querySelectorAll('.seek-studio-btn').forEach(btn => {
        btn.addEventListener('click', () => seekPreviewVideo(parseInt(btn.dataset.seconds || '0', 10)));
    });

    // =========================================================================
    // FORM THÊM NHIỀU CÂU HỎI LIÊN TIẾP (BATCH BUILDER)
    // =========================================================================
    const container = document.getElementById('questionCardsContainer');
    const batchCountBadge = document.getElementById('batchCountBadge');
    const submitBatchBtn = document.getElementById('submitBatchBtn');
    const batchForm = document.getElementById('batchCreateForm');
    let uidCounter = 0;

    function createQuestionCard(defaultTimestamp = '00:15', defaultType = 'multiple_choice') {
        const uid = ++uidCounter;
        const card = document.createElement('div');
        card.className = 'question-builder-card border rounded-3 p-3 bg-light';
        card.dataset.uid = uid;
        card.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                <span class="fw-bold small text-primary card-title-label">Câu hỏi #1</span>
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-card-btn d-none" title="Xóa câu hỏi này khỏi danh sách thêm">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-sm-5">
                    <label class="form-label small fw-semibold mb-1">Mốc thời gian <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <input type="text" data-field="timestamp_input" class="form-control font-monospace ts-input" value="${defaultTimestamp}" placeholder="01:30" required>
                        <button type="button" class="btn btn-outline-primary capture-ts-btn" title="Lấy thời gian hiện tại của video">
                            <i class="bi bi-stopwatch"></i>
                        </button>
                    </div>
                </div>
                <div class="col-sm-7">
                    <label class="form-label small fw-semibold mb-1">Loại câu hỏi</label>
                    <select data-field="question_type" class="form-select form-select-sm type-select">
                        <option value="multiple_choice" ${defaultType === 'multiple_choice' ? 'selected' : ''}>Trắc nghiệm (1 đáp án đúng)</option>
                        <option value="true_false" ${defaultType === 'true_false' ? 'selected' : ''}>Đúng / Sai</option>
                        <option value="short_answer" ${defaultType === 'short_answer' ? 'selected' : ''}>Trả lời ngắn</option>
                    </select>
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label small fw-semibold mb-1">Nội dung câu hỏi <span class="text-danger">*</span></label>
                <textarea data-field="question" rows="2" class="form-control form-control-sm" placeholder="Nhập câu hỏi hiển thị khi video dừng..." required></textarea>
            </div>

            <!-- Trắc nghiệm (1 đáp án đúng duy nhất) -->
            <div class="mc-group mb-2 p-2 bg-white rounded border">
                <div class="small text-muted mb-2">
                    Nhập ít nhất 2 phương án và <strong>tích chọn 1 đáp án đúng</strong>:
                </div>
                <div class="row g-2">
                    ${['A', 'B', 'C', 'D'].map((letter, i) => `
                        <div class="col-sm-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light">
                                    <input class="form-check-input mt-0 mc-correct-radio" type="radio" name="mc_radio_${uid}" value="${letter}" ${i === 0 ? 'checked' : ''} title="Chọn ${letter} là đáp án đúng">
                                </span>
                                <span class="input-group-text fw-bold">${letter}</span>
                                <input type="text" data-field="option_${letter.toLowerCase()}" class="form-control" placeholder="Phương án ${letter}${i >= 2 ? ' (tùy chọn)' : ''}">
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>

            <!-- Đúng / Sai -->
            <div class="tf-group mb-2 p-2 bg-white rounded border d-none">
                <div class="small fw-semibold text-muted mb-1">Chọn đáp án đúng:</div>
                <div class="d-flex gap-4">
                    <div class="form-check">
                        <input class="form-check-input tf-correct-radio" type="radio" name="tf_radio_${uid}" id="tf_t_${uid}" value="true" checked>
                        <label class="form-check-label fw-semibold text-success small" for="tf_t_${uid}">Đúng</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input tf-correct-radio" type="radio" name="tf_radio_${uid}" id="tf_f_${uid}" value="false">
                        <label class="form-check-label fw-semibold text-danger small" for="tf_f_${uid}">Sai</label>
                    </div>
                </div>
            </div>

            <!-- Trả lời ngắn -->
            <div class="short-group mb-2 p-2 bg-white rounded border d-none">
                <label class="form-label small fw-semibold mb-1">Đáp án đúng (nhiều đáp án cách nhau bởi |)</label>
                <input type="text" class="form-control form-control-sm short-correct-input" placeholder="VD: 42 hoặc Hà Nội|Ha Noi">
            </div>

            <input type="hidden" data-field="correct_answer" class="hidden-correct-answer" value="A">

            <div class="mb-2">
                <input type="text" data-field="explanation" class="form-control form-control-sm" placeholder="Giải thích đáp án sau khi trả lời (tùy chọn)...">
            </div>

            <div class="form-check mb-0">
                <input type="hidden" data-field="is_required_off" value="0">
                <input class="form-check-input req-checkbox" type="checkbox" data-field="is_required" value="1" id="req_${uid}" checked>
                <label class="form-check-label small text-muted" for="req_${uid}">
                    Bắt buộc trả lời để tiếp tục xem video
                </label>
            </div>
        `;

        const typeSelect = card.querySelector('.type-select');
        const mcGroup = card.querySelector('.mc-group');
        const tfGroup = card.querySelector('.tf-group');
        const shortGroup = card.querySelector('.short-group');
        const hiddenCorrect = card.querySelector('.hidden-correct-answer');
        const shortInput = card.querySelector('.short-correct-input');

        function syncCardType() {
            const t = typeSelect.value;
            mcGroup.classList.toggle('d-none', t !== 'multiple_choice');
            tfGroup.classList.toggle('d-none', t !== 'true_false');
            shortGroup.classList.toggle('d-none', t !== 'short_answer');
            syncCardCorrect();
        }

        function syncCardCorrect() {
            const t = typeSelect.value;
            if (t === 'multiple_choice') {
                const checked = card.querySelector('.mc-correct-radio:checked');
                hiddenCorrect.value = checked ? checked.value : 'A';
            } else if (t === 'true_false') {
                const checked = card.querySelector('.tf-correct-radio:checked');
                hiddenCorrect.value = checked ? checked.value : 'true';
            } else {
                hiddenCorrect.value = shortInput.value;
            }
        }

        typeSelect.addEventListener('change', syncCardType);
        card.querySelectorAll('.mc-correct-radio, .tf-correct-radio').forEach(r => r.addEventListener('change', syncCardCorrect));
        shortInput.addEventListener('input', syncCardCorrect);
        card.syncCardCorrect = syncCardCorrect;
        syncCardType();

        card.querySelector('.capture-ts-btn').addEventListener('click', () => {
            pausePreviewVideo();
            const tsInput = card.querySelector('.ts-input');
            tsInput.value = formatMMSS(getCurrentSeconds());
            tsInput.focus();
        });

        card.querySelector('.remove-card-btn').addEventListener('click', () => {
            card.remove();
            reindexCards();
        });

        container.appendChild(card);
        reindexCards();
        return card;
    }

    function reindexCards() {
        const cards = container.querySelectorAll('.question-builder-card');
        cards.forEach((card, idx) => {
            card.querySelector('.card-title-label').textContent = `Câu hỏi #${idx + 1}`;
            card.querySelector('.remove-card-btn').classList.toggle('d-none', cards.length <= 1);
            card.querySelectorAll('[data-field]').forEach(el => {
                const field = el.dataset.field;
                if (field === 'is_required_off') {
                    el.name = `items[${idx}][is_required]`;
                } else {
                    el.name = `items[${idx}][${field}]`;
                }
            });
        });
        batchCountBadge.textContent = `${cards.length} câu hỏi`;
        submitBatchBtn.innerHTML = cards.length > 1
            ? `<i class="bi bi-save me-1"></i>Lưu tất cả ${cards.length} câu hỏi vào video`
            : `<i class="bi bi-save me-1"></i>Lưu câu hỏi vào video`;
    }

    document.getElementById('addSameTimestampBtn').addEventListener('click', () => {
        const cards = container.querySelectorAll('.question-builder-card');
        const lastCard = cards[cards.length - 1];
        const ts = lastCard ? lastCard.querySelector('.ts-input').value : '00:15';
        const type = lastCard ? lastCard.querySelector('.type-select').value : 'multiple_choice';
        const newCard = createQuestionCard(ts, type);
        newCard.querySelector('textarea').focus();
    });

    document.getElementById('addNewTimestampBtn').addEventListener('click', () => {
        const currentSec = getCurrentSeconds();
        const ts = currentSec > 0 ? formatMMSS(currentSec) : '00:30';
        const newCard = createQuestionCard(ts, 'multiple_choice');
        newCard.querySelector('.ts-input').focus();
    });

    batchForm.addEventListener('submit', () => {
        container.querySelectorAll('.question-builder-card').forEach(card => {
            if (typeof card.syncCardCorrect === 'function') card.syncCardCorrect();
        });
    });

    // Khởi tạo câu hỏi #1 mặc định
    createQuestionCard('00:15', 'multiple_choice');

    // =========================================================================
    // MODAL SỬA CÂU HỎI ĐÃ TẠO (HOẠT ĐỘNG ĐỘC LẬP KHÔNG PHỤ THUỘC window.bootstrap)
    // =========================================================================
    const editItemsMap = @json($editItemsMap);
    const editModalEl = document.getElementById('editInteractionModal');
    const editForm = document.getElementById('editInteractionForm');
    const editTsInput = document.getElementById('edit_timestamp_input');
    const editTypeSelect = document.getElementById('edit_question_type');
    const editQuestion = document.getElementById('edit_question');
    const editMcGroup = document.getElementById('editMcGroup');
    const editTfGroup = document.getElementById('editTfGroup');
    const editShortGroup = document.getElementById('editShortGroup');
    const editShortInput = document.getElementById('edit_short_correct');
    const editHiddenCorrect = document.getElementById('edit_correct_answer');
    const editExplanation = document.getElementById('edit_explanation');
    const editRequired = document.getElementById('edit_is_required');

    let editBackdropEl = null;
    function openEditModal() {
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(editModalEl).show();
            return;
        }
        editModalEl.classList.add('show');
        editModalEl.style.display = 'block';
        editModalEl.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
        if (!editBackdropEl) {
            editBackdropEl = document.createElement('div');
            editBackdropEl.className = 'modal-backdrop fade show';
            editBackdropEl.addEventListener('click', closeEditModal);
            document.body.appendChild(editBackdropEl);
        }
    }

    function closeEditModal() {
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(editModalEl).hide();
            return;
        }
        editModalEl.classList.remove('show');
        editModalEl.style.display = 'none';
        editModalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        if (editBackdropEl) {
            editBackdropEl.remove();
            editBackdropEl = null;
        }
    }

    editModalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', closeEditModal);
    });

    function syncEditTypeUI() {
        const t = editTypeSelect.value;
        editMcGroup.classList.toggle('d-none', t !== 'multiple_choice');
        editTfGroup.classList.toggle('d-none', t !== 'true_false');
        editShortGroup.classList.toggle('d-none', t !== 'short_answer');
        syncEditCorrectValue();
    }

    function syncEditCorrectValue() {
        const t = editTypeSelect.value;
        if (t === 'multiple_choice') {
            const checked = editForm.querySelector('.edit-mc-radio:checked');
            editHiddenCorrect.value = checked ? checked.value : 'A';
        } else if (t === 'true_false') {
            const checked = editForm.querySelector('input[name="edit_tf_correct"]:checked');
            editHiddenCorrect.value = checked ? checked.value : 'true';
        } else {
            editHiddenCorrect.value = editShortInput.value;
        }
    }

    editTypeSelect.addEventListener('change', syncEditTypeUI);
    editForm.querySelectorAll('.edit-mc-radio, input[name="edit_tf_correct"]').forEach(r => r.addEventListener('change', syncEditCorrectValue));
    editShortInput.addEventListener('input', syncEditCorrectValue);
    editForm.addEventListener('submit', syncEditCorrectValue);

    document.getElementById('editCaptureTimeBtn').addEventListener('click', () => {
        pausePreviewVideo();
        editTsInput.value = formatMMSS(getCurrentSeconds());
    });

    document.querySelectorAll('.edit-interaction-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const data = editItemsMap[id];
            if (!data) return;
            editForm.action = data.update_url;
            editTsInput.value = data.timestamp_input || '00:00';
            editTypeSelect.value = data.question_type || 'multiple_choice';
            editQuestion.value = data.question || '';
            editExplanation.value = data.explanation || '';
            editRequired.checked = !!data.is_required;

            ['A', 'B', 'C', 'D'].forEach(letter => {
                const input = document.getElementById('edit_option_' + letter.toLowerCase());
                if (input) input.value = (data.options && data.options[letter]) ? data.options[letter] : '';
            });

            if (data.question_type === 'multiple_choice') {
                const ans = String(data.correct_answer || 'A').toUpperCase();
                const radio = editForm.querySelector(`.edit-mc-radio[value="${ans}"]`);
                if (radio) radio.checked = true;
            } else if (data.question_type === 'true_false') {
                const val = data.correct_answer === 'true' ? 'true' : 'false';
                const radio = editForm.querySelector(`input[name="edit_tf_correct"][value="${val}"]`);
                if (radio) radio.checked = true;
            } else {
                editShortInput.value = data.correct_answer || '';
            }

            syncEditTypeUI();
            openEditModal();
        });
    });
});
</script>
@endpush
@endsection
