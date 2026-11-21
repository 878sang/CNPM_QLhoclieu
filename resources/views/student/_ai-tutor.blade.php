@php
    $aiPrepareUrl = route('student.ai-tutor.prepare', [$course, $currentLesson]);
    $aiStreamUrl = route('student.ai-tutor.stream', [$course, $currentLesson]);
    $aiAskUrl = route('student.ai-tutor.ask', [$course, $currentLesson]);
    $aiResetUrl = route('student.ai-tutor.reset', [$course, $currentLesson]);
    $aiShouldWarmup = ($aiConfigured ?? false) && ! ($aiContextReady ?? false);
    $aiHistoryPayload = ($aiMessages ?? collect())->map(fn ($m) => [
        'id' => $m->id,
        'role' => $m->role,
        'content' => $m->content,
        'image_url' => $m->imageUrl(),
        'formatted_timestamp' => $m->formattedTimestamp(),
        'video_timestamp_seconds' => $m->video_timestamp_seconds,
        'created_at' => $m->created_at?->format('H:i') ?? '',
    ])->values();
@endphp

<!-- KHỐI WIDGET TRỢ LÝ AI NỔI Ở GÓC DƯỚI PHẢI -->
<div class="ai-tutor-floating" id="aiTutorFloatingRoot">
    <!-- POPUP HỘI THOẠI (ẨN MẶC ĐỊNH, NHẤN NÚT DƯỚI ĐỂ MỞ TRỰC TIẾP TRÊN TRANG) -->
    <section
        id="aiTutorPopup"
        class="ai-tutor-popup"
        role="dialog"
        aria-modal="false"
        aria-label="Trợ lý AI Gia sư"
        aria-hidden="true"
    >
        <!-- HEADER POPUP -->
        <div class="ai-tutor-popup__header">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <span class="ai-tutor-popup__avatar flex-shrink-0">
                    <i class="bi bi-stars"></i>
                </span>
                <div class="min-w-0">
                    <div class="fw-bold text-white text-truncate" style="font-size: 0.92rem; line-height: 1.2;">
                        Trợ lý AI bài giảng
                    </div>
                    <div class="text-white-50 text-truncate" style="font-size: 0.72rem;">
                        Hỏi đáp video, tài liệu &amp; ảnh chụp bài tập
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                <button
                    type="button"
                    class="ai-tutor-icon-btn d-none d-md-inline-flex"
                    id="aiTutorExpandBtn"
                    title="Phóng to / Thu nhỏ khung chat"
                    aria-label="Phóng to khung chat"
                >
                    <i class="bi bi-arrows-angle-expand"></i>
                </button>
                <button
                    type="button"
                    class="ai-tutor-icon-btn"
                    id="aiTutorResetBtn"
                    title="Làm mới cuộc trò chuyện"
                    aria-label="Làm mới hội thoại"
                >
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
                <button
                    type="button"
                    class="ai-tutor-icon-btn"
                    id="aiSwitchToTeacherBtn"
                    title="Chuyển sang đặt câu hỏi cho giảng viên"
                    aria-label="Hỏi giảng viên"
                >
                    <i class="bi bi-person-raised-hand"></i>
                </button>
                <button
                    type="button"
                    class="ai-tutor-icon-btn"
                    id="aiTutorCloseBtn"
                    title="Đóng cửa sổ Trợ lý AI (Esc)"
                    aria-label="Đóng Trợ lý AI"
                >
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <!-- KHUNG TIN NHẮN HỘI THOẠI -->
        <div id="aiTutorMessages" class="ai-tutor-popup__messages"></div>

        <!-- GỢI Ý NHANH -->
        <div class="ai-tutor-popup__quick" id="aiQuickPrompts">
            @if($hasVideo)
                <button type="button" class="btn btn-sm btn-outline-primary ai-quick-btn" data-skip-ts="1" data-prompt="Hãy tóm tắt các ý chính trong video bài giảng này kèm các mốc thời gian quan trọng (mm:ss).">
                    <i class="bi bi-sparkles"></i>Tóm tắt video
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary ai-quick-btn" data-capture-ts="1" data-prompt="Hãy giải thích kỹ phần kiến thức thầy/cô đang giảng xung quanh đoạn video này.">
                    <i class="bi bi-stopwatch"></i>Giải thích đoạn này
                </button>
            @endif
            @if($attachedDocs->isNotEmpty())
                <button type="button" class="btn btn-sm btn-outline-secondary ai-quick-btn" data-skip-ts="1" data-prompt="Hãy tóm tắt trọng tâm tài liệu đính kèm của bài học này.">
                    <i class="bi bi-file-earmark-text"></i>Tóm tắt tài liệu
                </button>
            @endif
            <button type="button" class="btn btn-sm btn-outline-secondary ai-quick-btn" data-skip-ts="1" data-prompt="Hãy tạo giúp mình 3 câu hỏi trắc nghiệm ôn tập ngắn dựa trên bài học này (ẩn đáp án ở cuối).">
                <i class="bi bi-ui-checks"></i>3 câu ôn tập
            </button>
        </div>

        <!-- KHUNG NHẬP CÂU HỎI & ĐÍNH KÈM ẢNH -->
        <form id="aiTutorForm" class="ai-tutor-popup__footer" enctype="multipart/form-data">
            @if($hasVideo)
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <div class="form-check form-switch mb-0" style="font-size: 0.78rem;">
                        <input class="form-check-input" type="checkbox" id="aiIncludeTimestampCheck" checked>
                        <label class="form-check-label text-muted" for="aiIncludeTimestampCheck">
                            Kèm mốc video đang xem (<span class="font-monospace fw-semibold text-dark" id="aiLiveVideoTs">00:00</span>)
                        </label>
                    </div>
                </div>
            @endif

            <!-- XEM TRƯỚC ẢNH ĐÍNH KÈM -->
            <div id="aiImagePreviewWrap" class="ai-tutor-img-preview d-none mb-2">
                <img id="aiImagePreviewThumb" src="" alt="Ảnh đính kèm">
                <div class="min-w-0 flex-grow-1">
                    <div id="aiImagePreviewName" class="small fw-semibold text-truncate"></div>
                    <div id="aiImagePreviewMeta" class="text-muted" style="font-size: 0.72rem;">Ảnh sẽ được gửi kèm câu hỏi cho AI</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" id="aiImageRemoveBtn" title="Gỡ ảnh đính kèm">
                    <i class="bi bi-x"></i>
                </button>
            </div>

            <div id="aiTutorError" class="alert alert-danger py-2 px-2 small d-none mb-2" style="font-size: 0.8rem;"></div>

            <input
                type="file"
                id="aiTutorImageInput"
                class="d-none"
                accept="image/jpeg,image/png,image/webp,image/gif"
            >

            <div class="input-group input-group-sm">
                <button
                    type="button"
                    class="btn btn-outline-secondary px-2"
                    id="aiTutorImageBtn"
                    title="Gửi ảnh đề bài / công thức (hoặc dán Ctrl+V)"
                    aria-label="Đính kèm hình ảnh"
                >
                    <i class="bi bi-image"></i>
                </button>
                <input
                    type="text"
                    id="aiTutorInput"
                    class="form-control"
                    placeholder="Hỏi AI hoặc dán ảnh (Ctrl+V)..."
                    maxlength="2000"
                    autocomplete="off"
                >
                <button type="submit" class="btn btn-primary px-3 fw-semibold" id="aiTutorSendBtn" title="Gửi câu hỏi">
                    <i class="bi bi-send-fill"></i>
                </button>
            </div>
        </form>
    </section>

    <!-- NÚT NỔI MỞ/ĐÓNG TRỢ LÝ AI Ở PHÍA DƯỚI -->
    <button
        type="button"
        id="aiTutorFab"
        class="ai-tutor-fab"
        aria-expanded="false"
        aria-controls="aiTutorPopup"
        title="Mở Trợ lý AI hỏi đáp theo video, tài liệu & hình ảnh"
    >
        <span class="ai-tutor-fab__dot" aria-hidden="true"></span>
        <i class="bi bi-stars ai-tutor-fab__icon-open"></i>
        <i class="bi bi-chevron-down ai-tutor-fab__icon-close"></i>
        <span class="ai-tutor-fab__label">Trợ lý AI</span>
        @if(($aiMessages ?? collect())->isNotEmpty())
            <span class="ai-tutor-fab__badge" id="aiTutorMsgCount">{{ $aiMessages->count() }}</span>
        @else
            <span class="ai-tutor-fab__badge d-none" id="aiTutorMsgCount">0</span>
        @endif
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const prepareUrl = @json($aiPrepareUrl);
    const shouldWarmup = @json($aiShouldWarmup);
    const streamUrl = @json($aiStreamUrl);
    const askUrl = @json($aiAskUrl);
    const resetUrl = @json($aiResetUrl);
    const csrfToken = '{{ csrf_token() }}';
    const initialHistory = @json($aiHistoryPayload);

    // Đọc ngầm video & tài liệu bằng tiến trình nền độc lập (server trả về ngay ~15ms, không gây treo trang)
    if (shouldWarmup && prepareUrl) {
        setTimeout(() => {
            fetch(prepareUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            }).catch(() => {});
        }, 1200);
    }

    const popup = document.getElementById('aiTutorPopup');
    const fab = document.getElementById('aiTutorFab');
    const closeBtn = document.getElementById('aiTutorCloseBtn');
    const expandBtn = document.getElementById('aiTutorExpandBtn');
    const msgCountBadge = document.getElementById('aiTutorMsgCount');

    const messagesBox = document.getElementById('aiTutorMessages');
    const form = document.getElementById('aiTutorForm');
    const inputEl = document.getElementById('aiTutorInput');
    const sendBtn = document.getElementById('aiTutorSendBtn');
    const errorEl = document.getElementById('aiTutorError');
    const resetBtn = document.getElementById('aiTutorResetBtn');
    const includeTsCheck = document.getElementById('aiIncludeTimestampCheck');
    const liveTsEl = document.getElementById('aiLiveVideoTs');
    const switchTeacherBtn = document.getElementById('aiSwitchToTeacherBtn');

    const imageBtn = document.getElementById('aiTutorImageBtn');
    const imageInput = document.getElementById('aiTutorImageInput');
    const imagePreviewWrap = document.getElementById('aiImagePreviewWrap');
    const imagePreviewThumb = document.getElementById('aiImagePreviewThumb');
    const imagePreviewName = document.getElementById('aiImagePreviewName');
    const imagePreviewMeta = document.getElementById('aiImagePreviewMeta');
    const imageRemoveBtn = document.getElementById('aiImageRemoveBtn');

    if (!popup || !fab || !messagesBox || !form) return;

    let isOpen = false;
    let totalMessages = initialHistory.length;
    let pendingImageFile = null;
    let pendingImageObjectUrl = null;

    function updateBadgeCount(count) {
        totalMessages = count;
        if (!msgCountBadge) return;
        if (count > 0) {
            msgCountBadge.textContent = count > 99 ? '99+' : String(count);
            msgCountBadge.classList.remove('d-none');
        } else {
            msgCountBadge.classList.add('d-none');
        }
    }

    function formatKb(bytes) {
        if (!bytes) return '';
        if (bytes < 1024 * 1024) return Math.max(1, Math.round(bytes / 1024)) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function clearPendingImage(revokeUrl = true) {
        if (revokeUrl && pendingImageObjectUrl) {
            URL.revokeObjectURL(pendingImageObjectUrl);
        }
        pendingImageFile = null;
        pendingImageObjectUrl = null;
        if (imageInput) imageInput.value = '';
        if (imagePreviewWrap) imagePreviewWrap.classList.add('d-none');
        if (imagePreviewThumb) imagePreviewThumb.src = '';
    }

    // Tối ưu #4: Tự động nén & thu gọn ảnh lớn (<= 1280px, JPEG 0.84) ngay trên trình duyệt để upload nhanh gấp 5-10 lần
    function compressImageFile(file) {
        return new Promise((resolve) => {
            if (!file || file.type === 'image/gif') {
                resolve(file);
                return;
            }
            const maxDim = 1280;
            const img = new Image();
            const tmpUrl = URL.createObjectURL(file);

            img.onload = () => {
                URL.revokeObjectURL(tmpUrl);
                let { width, height } = img;
                const needsResize = width > maxDim || height > maxDim;
                const needsCompress = file.size > 350 * 1024;

                if (!needsResize && !needsCompress) {
                    resolve(file);
                    return;
                }

                if (needsResize) {
                    const ratio = Math.min(maxDim / width, maxDim / height);
                    width = Math.max(1, Math.round(width * ratio));
                    height = Math.max(1, Math.round(height * ratio));
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    resolve(file);
                    return;
                }
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, width, height);
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob((blob) => {
                    if (!blob || blob.size >= file.size) {
                        resolve(file);
                        return;
                    }
                    const baseName = (file.name || 'screenshot').replace(/\.[^.]+$/, '');
                    resolve(new File([blob], baseName + '.jpg', { type: 'image/jpeg' }));
                }, 'image/jpeg', 0.84);
            };

            img.onerror = () => {
                URL.revokeObjectURL(tmpUrl);
                resolve(file);
            };
            img.src = tmpUrl;
        });
    }

    async function attachImageFile(file) {
        if (!file) return;
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!allowed.includes(file.type)) {
            errorEl.textContent = 'Chỉ hỗ trợ ảnh định dạng JPG, PNG, WEBP hoặc GIF.';
            errorEl.classList.remove('d-none');
            return;
        }
        if (file.size > 12 * 1024 * 1024) {
            errorEl.textContent = 'Ảnh gốc quá lớn (tối đa 12MB).';
            errorEl.classList.remove('d-none');
            return;
        }
        errorEl.classList.add('d-none');
        clearPendingImage(true);

        const optimizedFile = await compressImageFile(file);
        if (optimizedFile.size > 5 * 1024 * 1024) {
            errorEl.textContent = 'Dung lượng ảnh sau khi nén vẫn vượt quá 5MB.';
            errorEl.classList.remove('d-none');
            return;
        }

        pendingImageFile = optimizedFile;
        pendingImageObjectUrl = URL.createObjectURL(optimizedFile);
        if (imagePreviewThumb) imagePreviewThumb.src = pendingImageObjectUrl;
        if (imagePreviewName) imagePreviewName.textContent = optimizedFile.name || 'Ảnh chụp màn hình';
        if (imagePreviewMeta) {
            imagePreviewMeta.textContent = optimizedFile.size < file.size
                ? `Đã nén tự động: ${formatKb(file.size)} → ${formatKb(optimizedFile.size)}`
                : `Dung lượng: ${formatKb(optimizedFile.size)}`;
        }
        if (imagePreviewWrap) imagePreviewWrap.classList.remove('d-none');
        inputEl.focus();
    }

    if (imageBtn && imageInput) {
        imageBtn.addEventListener('click', () => imageInput.click());
        imageInput.addEventListener('change', () => {
            if (imageInput.files && imageInput.files[0]) {
                attachImageFile(imageInput.files[0]);
            }
        });
    }

    if (imageRemoveBtn) {
        imageRemoveBtn.addEventListener('click', () => clearPendingImage(true));
    }

    // Cho phép dán trực tiếp ảnh chụp màn hình (Ctrl+V) vào khung chat
    inputEl.addEventListener('paste', function (e) {
        const items = e.clipboardData ? e.clipboardData.items : null;
        if (!items) return;
        for (const item of items) {
            if (item.type && item.type.startsWith('image/')) {
                const file = item.getAsFile();
                if (file) {
                    e.preventDefault();
                    attachImageFile(file);
                    break;
                }
            }
        }
    });

    function openPopup() {
        isOpen = true;
        popup.classList.add('is-open');
        popup.setAttribute('aria-hidden', 'false');
        fab.classList.add('is-open');
        fab.setAttribute('aria-expanded', 'true');
        if (liveTsEl) liveTsEl.textContent = formatMMSS(getCurrentPlayerSeconds());
        setTimeout(() => {
            messagesBox.scrollTop = messagesBox.scrollHeight;
            if (window.innerWidth >= 768) inputEl.focus();
        }, 120);
    }

    function closePopup() {
        isOpen = false;
        popup.classList.remove('is-open');
        popup.setAttribute('aria-hidden', 'true');
        fab.classList.remove('is-open');
        fab.setAttribute('aria-expanded', 'false');
    }

    fab.addEventListener('click', function () {
        if (isOpen) closePopup();
        else openPopup();
    });

    if (closeBtn) closeBtn.addEventListener('click', closePopup);

    if (expandBtn) {
        expandBtn.addEventListener('click', function () {
            const expanded = popup.classList.toggle('is-expanded');
            const icon = expandBtn.querySelector('i');
            if (icon) {
                icon.className = expanded ? 'bi bi-arrows-angle-contract' : 'bi bi-arrows-angle-expand';
            }
            expandBtn.title = expanded ? 'Thu nhỏ khung chat' : 'Phóng to khung chat';
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) closePopup();
    });

    function formatMMSS(sec) {
        sec = Math.max(0, Math.floor(sec || 0));
        return String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
    }

    function getCurrentPlayerSeconds() {
        const html5Video = document.getElementById('lessonVideoPlayer');
        if (html5Video) return Math.floor(html5Video.currentTime || 0);
        if (window.__learnMateYouTubePlayer && typeof window.__learnMateYouTubePlayer.getCurrentTime === 'function') {
            try { return Math.floor(window.__learnMateYouTubePlayer.getCurrentTime() || 0); } catch (e) {}
        }
        return 0;
    }

    if (liveTsEl) {
        setInterval(() => {
            if (isOpen) {
                liveTsEl.textContent = formatMMSS(getCurrentPlayerSeconds());
            }
        }, 1000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function renderLatexToken(tex, displayMode) {
        const cleanTex = (tex || '').trim();
        if (!cleanTex) return '';
        if (window.katex && typeof window.katex.renderToString === 'function') {
            try {
                const html = window.katex.renderToString(cleanTex, {
                    displayMode: !!displayMode,
                    throwOnError: false,
                    strict: false,
                    trust: false,
                });
                return displayMode
                    ? `<div class="ai-math-display">${html}</div>`
                    : `<span class="ai-math-inline">${html}</span>`;
            } catch (e) {}
        }
        return displayMode
            ? `<div class="ai-math-display font-monospace small">${escapeHtml(cleanTex)}</div>`
            : `<code class="px-1 rounded bg-light border">${escapeHtml(cleanTex)}</code>`;
    }

    // Trích xuất công thức LaTeX ($$...$$, \[...\], \(...\), $...$) trước khi xử lý Markdown để không bị vỡ ký tự * hoặc _
    function extractLatexBlocks(raw) {
        const mathTokens = [];
        const pushToken = (tex, displayMode) => {
            const idx = mathTokens.length;
            mathTokens.push({ tex, displayMode });
            return `@@AIMATH_${idx}@@`;
        };

        let text = raw == null ? '' : String(raw);
        // 1. Display math: $$ ... $$
        text = text.replace(/\$\$([\s\S]+?)\$\$/g, (_, tex) => pushToken(tex, true));
        // 2. Display math: \[ ... \]
        text = text.replace(/\\\[([\s\S]+?)\\\]/g, (_, tex) => pushToken(tex, true));
        // 3. Inline math: \( ... \)
        text = text.replace(/\\\((.+?)\\\)/g, (_, tex) => pushToken(tex, false));
        // 4. Inline math: $ ... $ (tránh số tiền hoặc dấu $ đơn lẻ)
        text = text.replace(/\$(?!\s)([^$\n]+?)(?<!\s)\$/g, (_, tex) => pushToken(tex, false));

        return { text, mathTokens };
    }

    function restoreLatexBlocks(html, mathTokens) {
        return html.replace(/@@AIMATH_(\d+)@@/g, (_, idx) => {
            const item = mathTokens[parseInt(idx, 10)];
            return item ? renderLatexToken(item.tex, item.displayMode) : '';
        });
    }

    function formatUserMessage(raw) {
        const { text, mathTokens } = extractLatexBlocks(raw);
        let safe = escapeHtml(text).replace(/\n/g, '<br>');
        return restoreLatexBlocks(safe, mathTokens);
    }

    function formatAiMarkdown(raw) {
        const { text, mathTokens } = extractLatexBlocks(raw);
        let safe = escapeHtml(text);
        // Tiêu đề Markdown: ### Tiêu đề
        safe = safe.replace(/^#{1,4}\s+(.+)$/gm, '<span class="ai-md-heading">$1</span>');
        // Đường kẻ ---
        safe = safe.replace(/^\s*---+\s*$/gm, '<hr class="my-2 opacity-25">');
        // **bold**
        safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // *italic* (không ăn vào dấu đầu dòng bullet)
        safe = safe.replace(/(^|[^*\w])\*([^*\n]+?)\*(?!\*)/g, '$1<em>$2</em>');
        // `code`
        safe = safe.replace(/`([^`]+)`/g, '<code class="px-1 rounded bg-light border">$1</code>');
        // Mốc thời gian mm:ss hoặc hh:mm:ss -> nút tua video trực tiếp
        safe = safe.replace(/\b(\d{1,2}):(\d{2})(?::(\d{2}))?\b/g, function (match, p1, p2, p3) {
            const totalSec = p3 !== undefined
                ? (parseInt(p1, 10) * 3600 + parseInt(p2, 10) * 60 + parseInt(p3, 10))
                : (parseInt(p1, 10) * 60 + parseInt(p2, 10));
            return `<button type="button" class="btn btn-dark btn-sm font-monospace py-0 px-2 mx-1 ai-jump-ts-btn" data-seconds="${totalSec}" title="Nhảy tới đoạn ${match} trên video"><i class="bi bi-play-fill"></i> ${match}</button>`;
        });
        safe = safe.replace(/\n/g, '<br>');
        // Khôi phục & render công thức LaTeX bằng KaTeX (gỡ <br> thừa quanh khối display math)
        safe = restoreLatexBlocks(safe, mathTokens);
        safe = safe.replace(/(<br>)+(<div class="ai-math-display">)/g, '$2');
        safe = safe.replace(/(<\/div>)(<br>)+/g, '$1');
        return safe;
    }

    function jumpToVideoSecond(targetSecs) {
        const videoTabBtn = document.getElementById('st-tab-video-head');
        if (videoTabBtn && !videoTabBtn.classList.contains('active')) {
            if (window.bootstrap && window.bootstrap.Tab) {
                window.bootstrap.Tab.getOrCreateInstance(videoTabBtn).show();
            } else {
                videoTabBtn.click();
            }
        }
        if (window.innerWidth < 768) {
            closePopup();
        }
        setTimeout(function () {
            const html5Video = document.getElementById('lessonVideoPlayer');
            const ytIframe = document.getElementById('lessonYouTubePlayer');
            if (html5Video) {
                html5Video.scrollIntoView({ behavior: 'smooth', block: 'center' });
                html5Video.currentTime = targetSecs;
                html5Video.play().catch(() => {});
            } else if (window.__learnMateYouTubePlayer && typeof window.__learnMateYouTubePlayer.seekTo === 'function') {
                if (ytIframe) ytIframe.scrollIntoView({ behavior: 'smooth', block: 'center' });
                try {
                    window.__learnMateYouTubePlayer.seekTo(targetSecs, true);
                    window.__learnMateYouTubePlayer.playVideo();
                } catch (e) {}
            }
        }, 140);
    }

    messagesBox.addEventListener('click', function (e) {
        const btn = e.target.closest('.ai-jump-ts-btn');
        if (!btn) return;
        const sec = parseInt(btn.dataset.seconds || '0', 10);
        jumpToVideoSecond(sec);
    });

    function renderEmptyGreeting() {
        messagesBox.innerHTML = `
            <div class="text-center text-muted my-auto py-4 px-2">
                <div class="ai-tutor-empty-icon mx-auto mb-2">
                    <i class="bi bi-stars"></i>
                </div>
                <div class="fw-semibold text-dark mb-1" style="font-size: 0.92rem;">Xin chào! Mình là Trợ lý AI của bài học này.</div>
                <div class="small" style="font-size: 0.8rem;">Bạn có thể vừa xem video vừa đặt câu hỏi, nhờ tóm tắt tài liệu, hoặc gửi ảnh chụp bài tập (chọn ảnh / Ctrl+V) để mình giải thích ngay.</div>
            </div>
        `;
    }

    function appendMessageBubble(msg) {
        const placeholder = messagesBox.querySelector('.my-auto');
        if (placeholder) placeholder.remove();

        const isUser = msg.role === 'user';
        const row = document.createElement('div');
        row.className = `d-flex ${isUser ? 'justify-content-end' : 'justify-content-start'}`;

        const bubble = document.createElement('div');
        bubble.className = isUser
            ? 'ai-tutor-bubble ai-tutor-bubble--user'
            : 'ai-tutor-bubble ai-tutor-bubble--assistant';

        if (isUser) {
            const tsChip = msg.formatted_timestamp
                ? `<span class="badge bg-white text-primary font-monospace me-1">Mốc ${escapeHtml(msg.formatted_timestamp)}</span>`
                : '';
            const imgHtml = msg.image_url
                ? `<a href="${escapeHtml(msg.image_url)}" target="_blank" rel="noopener" class="d-block mb-2"><img src="${escapeHtml(msg.image_url)}" alt="Ảnh đính kèm" class="ai-tutor-bubble__img"></a>`
                : '';
            const headerHtml = `<div class="opacity-75 mb-1 d-flex align-items-center gap-1" style="font-size: 0.72rem;">${tsChip}<span>Bạn · ${escapeHtml(msg.created_at || '')}</span></div>`;
            bubble.innerHTML = `${headerHtml}${imgHtml}<div class="lh-base">${formatUserMessage(msg.content)}</div>`;
        } else {
            const headerHtml = `<div class="fw-semibold text-primary mb-1" style="font-size: 0.74rem;"><i class="bi bi-stars me-1"></i>Trợ lý AI · <span class="text-muted fw-normal">${escapeHtml(msg.created_at || '')}</span></div>`;
            bubble.innerHTML = `${headerHtml}<div class="lh-base">${formatAiMarkdown(msg.content)}</div>`;
        }

        row.appendChild(bubble);
        messagesBox.appendChild(row);
        messagesBox.scrollTop = messagesBox.scrollHeight;
        return bubble;
    }

    function createStreamingAssistantBubble(timeStr) {
        const placeholder = messagesBox.querySelector('.my-auto');
        if (placeholder) placeholder.remove();

        const row = document.createElement('div');
        row.className = 'd-flex justify-content-start';

        const bubble = document.createElement('div');
        bubble.className = 'ai-tutor-bubble ai-tutor-bubble--assistant';
        bubble.innerHTML = `
            <div class="fw-semibold text-primary mb-1" style="font-size: 0.74rem;">
                <i class="bi bi-stars me-1"></i>Trợ lý AI · <span class="text-muted fw-normal">${escapeHtml(timeStr)}</span>
            </div>
            <div class="lh-base ai-stream-body"><span class="ai-tutor-cursor"></span></div>
        `;
        row.appendChild(bubble);
        messagesBox.appendChild(row);
        messagesBox.scrollTop = messagesBox.scrollHeight;
        return { row, bodyEl: bubble.querySelector('.ai-stream-body') };
    }

    function renderLoadingBubble() {
        const placeholder = messagesBox.querySelector('.my-auto');
        if (placeholder) placeholder.remove();

        const row = document.createElement('div');
        row.id = 'aiTutorLoadingBubble';
        row.className = 'd-flex justify-content-start';
        row.innerHTML = `
            <div class="ai-tutor-bubble ai-tutor-bubble--assistant text-muted small d-flex align-items-center gap-2">
                <span class="spinner-border spinner-border-sm text-primary flex-shrink-0"></span>
                <span>AI đang suy nghĩ...</span>
            </div>
        `;
        messagesBox.appendChild(row);
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    function removeLoadingBubble() {
        const el = document.getElementById('aiTutorLoadingBubble');
        if (el) el.remove();
    }

    if (initialHistory.length > 0) {
        initialHistory.forEach(appendMessageBubble);
    } else {
        renderEmptyGreeting();
    }

    // Tối ưu #1: Nhận phản hồi streaming SSE từng chữ hiện ra ngay lập tức
    async function sendQuestion(questionText, { forceIncludeTs = false, skipTs = false } = {}) {
        const text = (questionText || '').trim();
        const fileToSend = pendingImageFile;
        const previewUrlForBubble = pendingImageObjectUrl;

        if (!text && !fileToSend) {
            errorEl.textContent = 'Vui lòng nhập câu hỏi hoặc chọn hình ảnh để gửi.';
            errorEl.classList.remove('d-none');
            return;
        }

        errorEl.classList.add('d-none');
        errorEl.textContent = '';

        const useTs = !skipTs && (forceIncludeTs || (includeTsCheck && includeTsCheck.checked));
        const currentSecs = useTs ? getCurrentPlayerSeconds() : null;

        inputEl.value = '';
        inputEl.disabled = true;
        sendBtn.disabled = true;
        if (imageBtn) imageBtn.disabled = true;
        clearPendingImage(false);

        const displayQuestion = text || 'Hãy xem hình ảnh này và giải thích chi tiết giúp mình.';
        const nowStr = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
        appendMessageBubble({
            role: 'user',
            content: displayQuestion,
            image_url: previewUrlForBubble,
            formatted_timestamp: currentSecs !== null ? formatMMSS(currentSecs) : null,
            created_at: nowStr,
        });
        renderLoadingBubble();

        const formData = new FormData();
        if (text) formData.append('question', text);
        if (currentSecs !== null) formData.append('video_timestamp_seconds', String(currentSecs));
        if (fileToSend) formData.append('image', fileToSend);

        let streamBubble = null;
        let streamedText = '';

        try {
            const res = await fetch(streamUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'text/event-stream, application/json',
                },
                body: formData,
            });

            const contentType = res.headers.get('content-type') || '';

            // Nếu server trả lỗi validation/422 dạng JSON
            if (!res.ok || contentType.includes('application/json')) {
                const data = await res.json().catch(() => ({}));
                removeLoadingBubble();
                if (!res.ok || !data.success) {
                    errorEl.textContent = data.message || 'Không thể nhận phản hồi từ Trợ lý AI. Vui lòng thử lại.';
                    errorEl.classList.remove('d-none');
                } else if (data.assistant_message) {
                    appendMessageBubble(data.assistant_message);
                    updateBadgeCount(totalMessages + 2);
                }
                return;
            }

            if (!res.body || !res.body.getReader) {
                // Trình duyệt cũ không hỗ trợ ReadableStream -> fallback endpoint JSON
                const fallbackRes = await fetch(askUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: formData,
                });
                const fallbackData = await fallbackRes.json();
                removeLoadingBubble();
                if (!fallbackRes.ok || !fallbackData.success) {
                    errorEl.textContent = fallbackData.message || 'Không thể nhận phản hồi từ Trợ lý AI.';
                    errorEl.classList.remove('d-none');
                } else if (fallbackData.assistant_message) {
                    appendMessageBubble(fallbackData.assistant_message);
                    updateBadgeCount(totalMessages + 2);
                }
                return;
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let sseBuffer = '';

            const handleSseBlock = (block) => {
                const lines = block.split(/\r?\n/);
                let eventName = 'message';
                const dataLines = [];
                for (const line of lines) {
                    if (line.startsWith('event:')) {
                        eventName = line.slice(6).trim();
                    } else if (line.startsWith('data:')) {
                        dataLines.push(line.slice(5).trim());
                    }
                }
                if (!dataLines.length) return;

                let payload = null;
                try {
                    payload = JSON.parse(dataLines.join('\n'));
                } catch (e) {
                    return;
                }

                if (eventName === 'delta' && payload.text) {
                    if (!streamBubble) {
                        removeLoadingBubble();
                        streamBubble = createStreamingAssistantBubble(nowStr);
                    }
                    streamedText += payload.text;
                    streamBubble.bodyEl.innerHTML = formatAiMarkdown(streamedText) + '<span class="ai-tutor-cursor"></span>';
                    messagesBox.scrollTop = messagesBox.scrollHeight;
                } else if (eventName === 'done') {
                    removeLoadingBubble();
                    const finalContent = (payload.assistant_message && payload.assistant_message.content) || streamedText;
                    if (!streamBubble) {
                        appendMessageBubble({
                            role: 'assistant',
                            content: finalContent,
                            created_at: (payload.assistant_message && payload.assistant_message.created_at) || nowStr,
                        });
                    } else {
                        streamBubble.bodyEl.innerHTML = formatAiMarkdown(finalContent);
                    }
                    updateBadgeCount(totalMessages + 2);
                    messagesBox.scrollTop = messagesBox.scrollHeight;
                } else if (eventName === 'error') {
                    removeLoadingBubble();
                    if (streamBubble && !streamedText) {
                        streamBubble.row.remove();
                    }
                    errorEl.textContent = payload.message || 'Không thể nhận phản hồi từ Trợ lý AI. Vui lòng thử lại.';
                    errorEl.classList.remove('d-none');
                }
            };

            while (true) {
                const { value, done } = await reader.read();
                if (value) {
                    sseBuffer += decoder.decode(value, { stream: true });
                    const parts = sseBuffer.split(/\r?\n\r?\n/);
                    sseBuffer = parts.pop() || '';
                    for (const part of parts) {
                        if (part.trim()) handleSseBlock(part);
                    }
                }
                if (done) {
                    if (sseBuffer.trim()) handleSseBlock(sseBuffer);
                    break;
                }
            }
        } catch (err) {
            removeLoadingBubble();
            if (streamBubble && !streamedText) {
                streamBubble.row.remove();
            }
            errorEl.textContent = 'Lỗi kết nối mạng khi gọi Trợ lý AI. Vui lòng thử lại sau.';
            errorEl.classList.remove('d-none');
        } finally {
            inputEl.disabled = false;
            sendBtn.disabled = false;
            if (imageBtn) imageBtn.disabled = false;
            inputEl.focus();
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        sendQuestion(inputEl.value);
    });

    document.querySelectorAll('.ai-quick-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const prompt = btn.dataset.prompt || '';
            const captureTs = btn.dataset.captureTs === '1';
            const skipTs = btn.dataset.skipTs === '1';
            sendQuestion(prompt, { forceIncludeTs: captureTs, skipTs });
        });
    });

    if (resetBtn) {
        resetBtn.addEventListener('click', async () => {
            if (!confirm('Xóa lịch sử hỏi đáp AI của bài học này để bắt đầu cuộc trò chuyện mới?')) return;
            try {
                await fetch(resetUrl, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });
                renderEmptyGreeting();
                updateBadgeCount(0);
                clearPendingImage(true);
                errorEl.classList.add('d-none');
            } catch (e) {}
        });
    }

    if (switchTeacherBtn) {
        switchTeacherBtn.addEventListener('click', () => {
            closePopup();
            const qaTabBtn = document.getElementById('st-tab-interactions-head');
            if (qaTabBtn) {
                if (window.bootstrap && window.bootstrap.Tab) {
                    window.bootstrap.Tab.getOrCreateInstance(qaTabBtn).show();
                } else {
                    qaTabBtn.click();
                }
            }
            const questionSubtab = document.getElementById('subtab-question-head');
            if (questionSubtab) {
                if (window.bootstrap && window.bootstrap.Tab) {
                    window.bootstrap.Tab.getOrCreateInstance(questionSubtab).show();
                } else {
                    questionSubtab.click();
                }
            }
            setTimeout(() => {
                const qaPane = document.getElementById('subtab-question-pane');
                const textarea = qaPane ? qaPane.querySelector('textarea[name="content"]') : null;
                if (textarea) {
                    textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    textarea.focus();
                }
            }, 160);
        });
    }
});
</script>
