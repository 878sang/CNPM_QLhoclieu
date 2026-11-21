@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')

@section('content')
<x-page-header
    eyebrow="HỎI ĐÁP & THẢO LUẬN"
    title="Quản lý hỏi đáp & Thảo luận"
    description="Xem trực tiếp video bài giảng tại đúng mốc thời gian học viên thắc mắc để đối chiếu và giải đáp nhanh."
/>

<!-- THỐNG KÊ NHANH -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs h-100">
            <div class="small text-muted">Câu hỏi chờ giải đáp</div>
            <div class="fs-3 fw-bold text-warning">{{ $stats['open_discussions'] }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs h-100">
            <div class="small text-muted">Câu hỏi đã giải đáp</div>
            <div class="fs-3 fw-bold text-success">{{ $stats['resolved_discussions'] }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs h-100">
            <div class="small text-muted">Bình luận & Thảo luận</div>
            <div class="fs-3 fw-bold text-primary">{{ $stats['discussions_count'] }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs h-100">
            <div class="small text-muted">Đang ghim nổi bật</div>
            <div class="fs-3 fw-bold text-info">{{ $stats['pinned_discussions'] }}</div>
        </div>
    </div>
</div>

<!-- 2 TAB TÁCH BIỆT: CÂU HỎI CHO GIẢNG VIÊN & BÌNH LUẬN THẢO LUẬN -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a
            class="nav-link fw-semibold {{ $tab === 'questions' ? 'active' : '' }}"
            href="{{ route('interactions.index', array_merge(request()->except(['page', 'status']), ['tab' => 'questions'])) }}"
        >
            <i class="bi bi-question-circle me-1 text-warning"></i>Câu hỏi cho giảng viên
            <span class="badge {{ $stats['open_discussions'] > 0 ? 'bg-warning text-dark' : 'bg-secondary' }} ms-1">
                {{ $stats['open_discussions'] > 0 ? $stats['open_discussions'].' chờ trả lời' : $stats['questions_count'] }}
            </span>
        </a>
    </li>
    <li class="nav-item">
        <a
            class="nav-link fw-semibold {{ $tab === 'discussions' ? 'active' : '' }}"
            href="{{ route('interactions.index', array_merge(request()->except(['page', 'status']), ['tab' => 'discussions'])) }}"
        >
            <i class="bi bi-chat-left-text me-1 text-primary"></i>Bình luận & Thảo luận
            <span class="badge bg-primary ms-1">{{ $stats['discussions_count'] }}</span>
        </a>
    </li>
</ul>

<!-- BỘ LỌC -->
<x-card class="p-3 mb-4">
    <form method="GET" action="{{ route('interactions.index') }}" class="row g-2 align-items-center">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="col-md-3">
            <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Tất cả khóa học --</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id }}" @selected(request('course_id') == $c->id)>{{ $c->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Trạng thái --</option>
                @if($tab === 'questions')
                    <option value="open" @selected(request('status') === 'open')>Chờ giải đáp</option>
                    <option value="resolved" @selected(request('status') === 'resolved')>Đã giải đáp</option>
                    <option value="hidden" @selected(request('status') === 'hidden')>Đã ẩn</option>
                @else
                    <option value="open" @selected(request('status') === 'open')>Đang hiển thị</option>
                    <option value="hidden" @selected(request('status') === 'hidden')>Đã ẩn</option>
                @endif
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Tìm theo nội dung hoặc học viên...">
        </div>
        <div class="col-md-2">
            <div class="form-check small mb-0">
                <input class="form-check-input" type="checkbox" name="has_timestamp" value="1" id="filterHasTs" @checked(request()->boolean('has_timestamp')) onchange="this.form.submit()">
                <label class="form-check-label" for="filterHasTs">Có gắn mốc video</label>
            </div>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Lọc</button>
            @if(request()->hasAny(['course_id', 'status', 'q', 'has_timestamp']))
                <a href="{{ route('interactions.index', ['tab' => $tab]) }}" class="btn btn-sm btn-outline-secondary">Xóa</a>
            @endif
        </div>
    </form>
</x-card>

@php
    $videoLookupMap = [];
    foreach ($interactions as $it) {
        $vDoc = $it->lesson?->documents->first(fn ($d) => $d->isVideo());
        if ($vDoc) {
            $videoLookupMap[$it->id] = [
                'interaction_id' => $it->id,
                'course_title' => $it->course?->title ?? '',
                'lesson_title' => $it->lesson?->title ?? '',
                'video_title' => $vDoc->title,
                'student_name' => $it->user?->name ?? 'Học viên',
                'content' => $it->content,
                'timestamp_seconds' => $it->video_timestamp_seconds,
                'formatted_timestamp' => $it->formattedVideoTimestamp(),
                'source_type' => $vDoc->file_path ? 'html5' : ($vDoc->isYouTube() ? 'youtube' : 'embed'),
                'video_url' => $vDoc->file_path
                    ? route('courses.documents.show', [$it->course_id, $vDoc->id])
                    : ($vDoc->embed_url ?? $vDoc->url),
                'mime_type' => $vDoc->mime_type ?: 'video/mp4',
                'youtube_id' => ($vDoc->isYouTube() && preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', (string) $vDoc->url, $m)) ? $m[1] : null,
            ];
        }
    }
    $hasAnyVideo = count($videoLookupMap) > 0;
@endphp

<!-- BỐ CỤC 2 CỘT: DANH SÁCH HỎI ĐÁP + TRÌNH PHÁT VIDEO ĐỐI CHIẾU -->
<div class="row g-4 align-items-start">
    <!-- CỘT TRÁI: DANH SÁCH CÂU HỎI / BÌNH LUẬN -->
    <div class="{{ $hasAnyVideo ? 'col-lg-7' : 'col-12' }}">
        @forelse($interactions as $item)
            @php
                $itemHasVideo = array_key_exists($item->id, $videoLookupMap);
            @endphp
            <div
                id="qa-card-{{ $item->id }}"
                class="qa-interaction-card card border rounded-3 p-3 p-md-4 mb-3 bg-white shadow-2xs {{ $item->is_pinned ? 'border-primary' : '' }}"
            >
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <span class="lm-avatar flex-shrink-0" style="width: 34px; height: 34px; font-size: 0.85rem;">
                            {{ mb_strtoupper(mb_substr($item->user?->name ?? 'U', 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <div class="fw-semibold text-dark d-flex flex-wrap align-items-center gap-2">
                                <span>{{ $item->user?->name }}</span>
                                <span class="badge bg-light text-dark border small">{{ $item->user?->role?->display_name ?? 'Học viên' }}</span>
                                @if($item->is_pinned)
                                    <span class="badge bg-primary-subtle text-primary border border-primary"><i class="bi bi-pin-angle-fill me-1"></i>Đã ghim</span>
                                @endif
                                @if($item->isQuestion() || $item->status === 'hidden')
                                    <span class="badge {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span>
                                @endif
                            </div>
                            <div class="small text-muted">
                                Khóa: <strong>{{ $item->course?->title }}</strong>
                                @if($item->lesson) · Bài: {{ $item->lesson->title }} @endif
                                · {{ $item->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>

                    <!-- THAO TÁC QUẢN LÝ -->
                    <div class="d-flex flex-wrap gap-1 flex-shrink-0">
                        <form method="POST" action="{{ route('interactions.pin', $item) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2" title="{{ $item->is_pinned ? 'Bỏ ghim' : 'Ghim lên đầu' }}">
                                <i class="bi {{ $item->is_pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle' }}"></i>
                            </button>
                        </form>

                        @if($item->isQuestion())
                            @if($item->status !== 'resolved')
                                <form method="POST" action="{{ route('interactions.status', $item) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="resolved">
                                    <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2" title="Đánh dấu đã giải đáp">
                                        <i class="bi bi-check-lg"></i> Đã giải đáp
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('interactions.status', $item) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="open">
                                    <button type="submit" class="btn btn-sm btn-outline-warning py-0 px-2" title="Mở lại">Mở lại</button>
                                </form>
                            @endif
                        @endif

                        <form method="POST" action="{{ route('interactions.status', $item) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $item->status === 'hidden' ? 'open' : 'hidden' }}">
                            <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2" title="{{ $item->status === 'hidden' ? 'Hiện lại' : 'Ẩn nội dung' }}">
                                <i class="bi {{ $item->status === 'hidden' ? 'bi-eye' : 'bi-eye-slash' }}"></i>
                            </button>
                        </form>

                        <form method="POST" action="{{ route('interactions.destroy', $item) }}" onsubmit="return confirm('Xóa nội dung này?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Xóa">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- THANH MỞ ĐÚNG ĐOẠN VIDEO THẮC MẮC -->
                @if($itemHasVideo)
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-2 px-3 mb-2 rounded border bg-primary-subtle">
                        <div class="small text-dark d-flex align-items-center gap-2 min-w-0">
                            <i class="bi bi-camera-video-fill text-danger flex-shrink-0"></i>
                            <span class="text-truncate">
                                <strong>{{ $videoLookupMap[$item->id]['video_title'] }}</strong>
                                @if($item->formattedVideoTimestamp())
                                    — Học viên hỏi tại mốc <span class="badge bg-dark font-monospace">{{ $item->formattedVideoTimestamp() }}</span>
                                @endif
                            </span>
                        </div>
                        <button
                            type="button"
                            class="btn btn-sm btn-primary py-0 px-2 open-qa-video-btn flex-shrink-0"
                            data-interaction-id="{{ $item->id }}"
                        >
                            <i class="bi bi-play-fill me-1"></i>
                            {{ $item->formattedVideoTimestamp() ? 'Xem đoạn '.$item->formattedVideoTimestamp() : 'Xem video bài giảng' }}
                        </button>
                    </div>
                @elseif($item->formattedVideoTimestamp())
                    <div class="mb-2">
                        <span class="badge bg-dark font-monospace">Mốc {{ $item->formattedVideoTimestamp() }}</span>
                    </div>
                @endif

                <div class="p-3 bg-light rounded border mb-3" style="white-space: pre-line;">{{ $item->content }}</div>
                @include('interactions._edit', ['interaction' => $item])

                <!-- CÁC PHẢN HỒI -->
                @if($item->replies->isNotEmpty())
                    <div class="ms-3 ps-3 border-start border-2 mb-3">
                        @foreach($item->replies as $reply)
                            <div class="mb-2 p-2 px-3 bg-white rounded border">
                                <div class="d-flex justify-content-between align-items-center small mb-1">
                                    <div>
                                        <strong class="text-dark">{{ $reply->user?->name }}</strong>
                                        @if($reply->user?->hasRole(['admin', 'teacher']))
                                            <span class="badge bg-primary ms-1">Giảng viên / Quản trị</span>
                                        @endif
                                        <span class="text-muted ms-2">{{ $reply->created_at->diffForHumans() }}</span>
                                    </div>
                                    <form method="POST" action="{{ route('interactions.destroy', $reply) }}" onsubmit="return confirm('Xóa phản hồi này?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                                <div class="small text-dark" style="white-space: pre-line;">{{ $reply->content }}</div>
                                @include('interactions._edit', ['interaction' => $reply])
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- FORM TRẢ LỜI TRỰC TIẾP -->
                <form method="POST" action="{{ route('interactions.reply', $item) }}" class="mt-2">
                    @csrf
                    <div class="input-group input-group-sm">
                        <input type="text" name="content" class="form-control" placeholder="{{ $item->isQuestion() ? 'Nhập câu trả lời giải đáp cho học viên...' : 'Nhập bình luận phản hồi...' }}" required>
                        <button type="submit" class="btn btn-primary px-3"><i class="bi bi-send me-1"></i>Trả lời</button>
                    </div>
                    @if($item->isQuestion())
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="mark_resolved" value="1" id="resolveCheck{{ $item->id }}" checked>
                            <label class="form-check-label small text-muted" for="resolveCheck{{ $item->id }}">
                                Tự động đánh dấu "Đã giải đáp" và gửi thông báo tới học viên
                            </label>
                        </div>
                    @endif
                </form>
            </div>
        @empty
            <x-card class="p-4">
                <x-empty-state
                    :icon="$tab === 'questions' ? 'question-circle' : 'chat-dots'"
                    :title="$tab === 'questions' ? 'Chưa có câu hỏi nào gửi cho giảng viên' : 'Chưa có bình luận hay thảo luận nào'"
                    :message="$tab === 'questions' ? 'Khi học viên đặt câu hỏi cần giải đáp trong bài giảng, các câu hỏi sẽ hiển thị tại đây.' : 'Các bình luận và trao đổi giữa học viên trong bài học sẽ hiển thị tại đây.'"
                />
            </x-card>
        @endforelse

        <div class="mt-3">{{ $interactions->links('pagination::bootstrap-5') }}</div>
    </div>

    <!-- CỘT PHẢI: TRÌNH XEM VIDEO ĐỐI CHIẾU CHỖ HỌC VIÊN THẮC MẮC (STICKY) -->
    @if($hasAnyVideo)
        <div class="col-lg-5">
            <div class="card border rounded-3 overflow-hidden shadow-sm sticky-top bg-white" style="top: 80px;" id="qaVideoContextPanel">
                <div class="bg-primary text-white p-3 d-flex justify-content-between align-items-center gap-2">
                    <div class="min-w-0">
                        <div class="small text-white-50">VIDEO ĐỐI CHIẾU THẮC MẮC</div>
                        <div class="fw-bold text-truncate" id="qaVideoTitle">Chọn câu hỏi có video để xem</div>
                    </div>
                    <span class="badge bg-white text-primary font-monospace flex-shrink-0" id="qaVideoTargetBadge">00:00</span>
                </div>

                <div class="p-3">
                    <div class="small text-muted mb-2" id="qaVideoMeta"></div>

                    <!-- KHUNG PHÁT VIDEO -->
                    <div id="qaVideoPlayerBox" class="rounded overflow-hidden bg-black mb-2" style="min-height: 220px;">
                        <div class="d-flex flex-column align-items-center justify-content-center text-white-50 p-4" style="min-height: 220px;">
                            <i class="bi bi-play-circle fs-1 mb-2"></i>
                            <span class="small">Bấm "Xem đoạn ..." trên câu hỏi để nhảy tới đúng vị trí học viên thắc mắc</span>
                        </div>
                    </div>

                    <!-- ĐIỀU KHIỂN NHẢY NHANH MỐC THẮC MẮC -->
                    <div class="d-flex flex-wrap gap-1 mb-3" id="qaVideoControls">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="qaSeekBackBtn" title="Lùi lại 5 giây để nghe ngữ cảnh trước câu hỏi">
                            <i class="bi bi-skip-backward-fill me-1"></i>-5s
                        </button>
                        <button type="button" class="btn btn-sm btn-primary flex-grow-1 fw-semibold" id="qaSeekExactBtn">
                            <i class="bi bi-bullseye me-1"></i>Tới đúng mốc thắc mắc (<span id="qaSeekExactLabel">00:00</span>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="qaSeekFwdBtn" title="Tiến thêm 5 giây">
                            +5s<i class="bi bi-skip-forward-fill ms-1"></i>
                        </button>
                    </div>

                    <!-- TRÍCH DẪN NỘI DUNG HỌC VIÊN HỎI -->
                    <div class="p-2 px-3 rounded border bg-light small">
                        <div class="fw-semibold text-primary mb-1" id="qaVideoStudentLabel">Nội dung thắc mắc:</div>
                        <div class="text-dark" id="qaVideoQuestionText" style="white-space: pre-line;">—</div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@if($hasAnyVideo)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const videoMap = @json($videoLookupMap);
    const ids = Object.keys(videoMap);
    if (!ids.length) return;

    const playerBox = document.getElementById('qaVideoPlayerBox');
    const titleEl = document.getElementById('qaVideoTitle');
    const metaEl = document.getElementById('qaVideoMeta');
    const badgeEl = document.getElementById('qaVideoTargetBadge');
    const exactLabelEl = document.getElementById('qaSeekExactLabel');
    const studentLabelEl = document.getElementById('qaVideoStudentLabel');
    const questionTextEl = document.getElementById('qaVideoQuestionText');
    const seekBackBtn = document.getElementById('qaSeekBackBtn');
    const seekExactBtn = document.getElementById('qaSeekExactBtn');
    const seekFwdBtn = document.getElementById('qaSeekFwdBtn');

    let activeData = null;
    let currentHtml5Video = null;
    let activeTargetSeconds = 0;

    function formatMMSS(sec) {
        sec = Math.max(0, Math.floor(sec || 0));
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function renderVideoAt(data, seconds, autoplay = true) {
        if (!data) return;
        const seekSec = Math.max(0, Math.floor(seconds || 0));

        if (data.source_type === 'html5') {
            if (!currentHtml5Video || currentHtml5Video.dataset.src !== data.video_url) {
                playerBox.innerHTML = '';
                const video = document.createElement('video');
                video.controls = true;
                video.className = 'w-100';
                video.style.maxHeight = '300px';
                video.style.background = '#000';
                video.dataset.src = data.video_url;
                const source = document.createElement('source');
                source.src = data.video_url;
                source.type = data.mime_type || 'video/mp4';
                video.appendChild(source);
                playerBox.appendChild(video);
                currentHtml5Video = video;

                video.addEventListener('loadedmetadata', () => {
                    if (seekSec > 0 && seekSec < (video.duration || Infinity)) {
                        video.currentTime = seekSec;
                    }
                    if (autoplay) video.play().catch(() => {});
                }, { once: true });
            } else {
                currentHtml5Video.currentTime = seekSec;
                if (autoplay) currentHtml5Video.play().catch(() => {});
            }
        } else if (data.source_type === 'youtube' && data.youtube_id) {
            currentHtml5Video = null;
            const auto = autoplay ? 1 : 0;
            const embedSrc = `https://www.youtube.com/embed/${data.youtube_id}?start=${seekSec}&autoplay=${auto}&rel=0`;
            playerBox.innerHTML = `
                <div class="ratio ratio-16x9">
                    <iframe src="${embedSrc}" title="${escapeHtml(data.video_title)}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
            `;
        } else {
            currentHtml5Video = null;
            playerBox.innerHTML = `
                <div class="ratio ratio-16x9">
                    <iframe src="${escapeHtml(data.video_url)}" title="${escapeHtml(data.video_title)}" allowfullscreen></iframe>
                </div>
            `;
        }
    }

    function loadInteractionVideo(interactionId, autoplay = true) {
        const data = videoMap[interactionId];
        if (!data) return;
        activeData = data;
        activeTargetSeconds = data.timestamp_seconds !== null ? Number(data.timestamp_seconds) : 0;

        // Highlight active card
        document.querySelectorAll('.qa-interaction-card').forEach(c => c.classList.remove('border-2', 'border-primary', 'shadow'));
        const activeCard = document.getElementById('qa-card-' + interactionId);
        if (activeCard) activeCard.classList.add('border-2', 'border-primary', 'shadow');

        titleEl.textContent = data.video_title;
        metaEl.textContent = `${data.course_title}${data.lesson_title ? ' · ' + data.lesson_title : ''}`;
        const formatted = formatMMSS(activeTargetSeconds);
        badgeEl.textContent = data.formatted_timestamp ? 'Mốc ' + data.formatted_timestamp : 'Từ đầu';
        exactLabelEl.textContent = formatted;
        studentLabelEl.textContent = `${data.student_name} hỏi${data.formatted_timestamp ? ' tại mốc ' + data.formatted_timestamp : ''}:`;
        questionTextEl.textContent = data.content;

        // Nhảy tới đúng mốc thắc mắc (lùi 2 giây để giảng viên nghe trọn ý câu nói)
        const contextStart = data.timestamp_seconds !== null ? Math.max(0, activeTargetSeconds - 2) : 0;
        renderVideoAt(data, contextStart, autoplay);
    }

    document.querySelectorAll('.open-qa-video-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.interactionId;
            loadInteractionVideo(id, true);
            if (window.innerWidth < 992) {
                document.getElementById('qaVideoContextPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    seekExactBtn.addEventListener('click', () => {
        if (!activeData) return;
        renderVideoAt(activeData, activeTargetSeconds, true);
    });

    seekBackBtn.addEventListener('click', () => {
        if (!activeData) return;
        const base = currentHtml5Video ? currentHtml5Video.currentTime : activeTargetSeconds;
        renderVideoAt(activeData, Math.max(0, base - 5), true);
    });

    seekFwdBtn.addEventListener('click', () => {
        if (!activeData) return;
        const base = currentHtml5Video ? currentHtml5Video.currentTime : activeTargetSeconds;
        renderVideoAt(activeData, base + 5, true);
    });

    // Tự động nạp sẵn video của câu hỏi đầu tiên có gắn mốc (hoặc câu hỏi có video đầu tiên), không autoplay
    const firstWithTs = ids.find(id => videoMap[id].timestamp_seconds !== null) || ids[0];
    if (firstWithTs) {
        loadInteractionVideo(firstWithTs, false);
    }
});
</script>
@endpush
@endif
@endsection
