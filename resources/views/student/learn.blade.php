@extends('layouts.student')

@section('content')
@php
    $user = auth()->user();
    $isCurrentLessonCompleted = $currentLesson ? in_array($currentLesson->id, $completedLessonIds) : false;
    $initialPosition = $currentProgress?->last_position_seconds ?? 0;
    
    $videoDoc = $currentLesson ? $currentLesson->documents->first(fn ($d) => $d->isVideo()) : null;
    $attachedDocs = $currentLesson ? $currentLesson->documents->filter(fn ($d) => !$d->isVideo()) : collect();
    $hasVideo = (bool) $videoDoc;
    $defaultTab = $hasVideo ? 'video' : 'docs';
    $lessonPercentage = $currentLesson ? $currentLesson->progressPercentageFor($user) : 0;
@endphp

<div class="card border rounded-3 overflow-hidden shadow-sm bg-white mb-4">
    <!-- THANH HEADER TRÊN CÙNG MÀU XANH DƯƠNG CHUẨN THEO MẪU -->
    <div class="bg-primary text-white py-2 px-3 px-md-4 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('student.courses.show', $course) }}" class="btn btn-sm btn-outline-light border-0 fw-semibold text-white px-2">
                <i class="bi bi-arrow-left me-1"></i> Trở lại
            </a>
            <div class="vr bg-white opacity-25 d-none d-sm-block" style="height: 20px;"></div>
            <span class="badge bg-white text-primary fw-bold d-none d-sm-inline-block">{{ $course->subject?->name }}</span>
            <span class="h6 mb-0 text-white fw-bold text-truncate d-none d-md-inline-block" style="max-width: 450px;">{{ $course->title }}</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <span class="small text-white-50">Tiến độ khóa học:</span>
                <span class="fw-bold text-white ms-1" id="course_percent_text">{{ $percentage }}%</span>
            </div>
            <div class="progress bg-white bg-opacity-25" style="width: 120px; height: 8px;">
                <div class="progress-bar bg-success" id="course_progress_bar" role="progressbar" style="width: {{ $percentage }}%" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <button class="btn btn-light btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#curriculumDrawer">
                <i class="bi bi-list me-1"></i>Mục lục
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="p-3 pb-0">
            <x-alert tone="success" class="mb-0">{{ session('status') }}</x-alert>
        </div>
    @endif
    @if (session('error'))
        <div class="p-3 pb-0">
            <x-alert tone="warning" class="mb-0">{{ session('error') }}</x-alert>
        </div>
    @endif

    <div class="row g-0">
        <!-- CỘT NỘI DUNG CHÍNH (TRÌNH XEM HỌC LIỆU / VIDEO / TÀI LIỆU) -->
        <div class="col-lg-8 col-xl-9 p-3 p-md-4 bg-white border-end">
            @if(! $currentLesson)
                <div class="p-5 text-center">
                    <x-empty-state
                        icon="journal-x"
                        title="Khóa học chưa có bài học nào"
                        message="Giảng viên đang biên soạn nội dung cho khóa học này. Vui lòng quay lại sau."
                    />
                </div>
            @else
                <!-- TIÊU ĐỀ BÀI HỌC VÀ TRẠNG THÁI TIẾN ĐỘ -->
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 pb-3 border-bottom">
                    <div>
                        <div class="small text-muted mb-1">
                            <i class="bi bi-folder2-open me-1 text-warning"></i>{{ $currentLesson->chapter?->title }}
                        </div>
                        <h2 class="h4 mb-0 fw-bold text-dark">{{ $currentLesson->title }}</h2>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="lesson_status_badge" class="badge {{ $isCurrentLessonCompleted ? 'bg-success' : ($lessonPercentage > 0 ? 'bg-primary' : 'bg-secondary') }} p-2">
                            <i class="bi {{ $isCurrentLessonCompleted ? 'bi-check-circle-fill' : ($lessonPercentage > 0 ? 'bi-play-circle' : 'bi-clock-history') }} me-1"></i>
                            <span id="lesson_status_text">
                                @if($isCurrentLessonCompleted)
                                    Đã hoàn thành (100%)
                                @elseif($lessonPercentage > 0)
                                    Tiến độ: {{ $lessonPercentage }}%
                                @else
                                    Chưa học
                                @endif
                            </span>
                        </span>

                        @if(! $hasVideo)
                            <form method="POST" action="{{ route('student.lessons.toggle', [$course, $currentLesson]) }}" id="toggle_progress_form">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $isCurrentLessonCompleted ? 'btn-outline-secondary' : 'btn-success' }}" id="toggle_progress_btn">
                                    <i class="bi {{ $isCurrentLessonCompleted ? 'bi-arrow-counterclockwise' : 'bi-check-lg' }} me-1"></i>
                                    {{ $isCurrentLessonCompleted ? 'Học lại bài này' : 'Đánh dấu hoàn thành' }}
                                </button>
                            </form>
                        @elseif($isCurrentLessonCompleted)
                            <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
                                <i class="bi bi-check-circle-fill me-1"></i>Đã hoàn thành video
                            </span>
                        @else
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-2 small" title="Bài học có video cần xem hết video để hoàn thành">
                                <i class="bi bi-info-circle me-1"></i>Xem hết video để hoàn thành
                            </span>
                        @endif
                    </div>
                </div>

                <!-- 2 TAB RÕ RÀNG: VIDEO BÀI GIẢNG VÀ TÀI LIỆU ĐÍNH KÈM -->
                <ul class="nav nav-pills nav-fill bg-light p-1 rounded-3 mb-3 border" id="studentLessonTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $defaultTab === 'video' ? 'active' : '' }} fw-bold py-2" id="st-tab-video-head" data-bs-toggle="pill" data-bs-target="#st-tab-video-pane" type="button" role="tab">
                            <i class="bi bi-camera-video me-1 text-danger"></i>Video bài giảng
                            @if($hasVideo)
                                <span class="badge bg-danger ms-1 text-white">1</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary ms-1">0</span>
                            @endif
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $defaultTab === 'docs' ? 'active' : '' }} fw-bold py-2" id="st-tab-docs-head" data-bs-toggle="pill" data-bs-target="#st-tab-docs-pane" type="button" role="tab">
                            <i class="bi bi-paperclip me-1 text-primary"></i>Tài liệu đính kèm
                            <span class="badge bg-primary ms-1 text-white">{{ $attachedDocs->count() }} file</span>
                        </button>
                    </li>
                </ul>

                <!-- NỘI DUNG 2 TAB HỌC TẬP -->
                <div class="tab-content" id="studentLessonTabsContent">
                    <!-- ============================================== -->
                    <!-- TAB 1: VIDEO BÀI GIẢNG                         -->
                    <!-- ============================================== -->
                    <div class="tab-pane fade {{ $defaultTab === 'video' ? 'show active' : '' }}" id="st-tab-video-pane" role="tabpanel">
                        @if($hasVideo)
                            <div class="card border rounded-3 p-3 bg-light shadow-2xs mb-3">
                                @if($videoDoc->file_path)
                                    <!-- TRÌNH PHÁT VIDEO HTML5 MP4 (TIẾN ĐỘ VIDEO = TIẾN ĐỘ BÀI HỌC) -->
                                    <div class="video-container bg-black rounded overflow-hidden shadow-sm">
                                        <video
                                            id="lessonVideoPlayer"
                                            controls
                                            controlsList="nodownload"
                                            class="w-100"
                                            style="max-height: 520px; background: #000;"
                                            data-save-url="{{ route('student.lessons.video_position', [$course, $currentLesson]) }}"
                                            data-initial-position="{{ $initialPosition }}"
                                        >
                                            <source src="{{ route('courses.documents.show', [$course, $videoDoc]) }}" type="{{ $videoDoc->mime_type ?? 'video/mp4' }}">
                                            Trình duyệt của bạn không hỗ trợ phát video HTML5.
                                        </video>
                                    </div>

                                    <!-- CẢNH BÁO GIỚI HẠN TUA TIẾN VIDEO -->
                                    <div id="seek_restriction_notice" class="alert alert-warning alert-dismissible fade show py-2 px-3 small d-none mt-2 mb-0" role="alert">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-shield-exclamation text-warning fs-5 me-2 flex-shrink-0"></i>
                                            <div>
                                                <strong>Không thể tua tới:</strong> Bạn chưa học đến đoạn này. Vui lòng xem tuần tự nội dung bài giảng. Bạn có thể tua lùi để ôn lại bài bất kỳ lúc nào.
                                            </div>
                                            <button type="button" class="btn-close ms-auto p-2" onclick="document.getElementById('seek_restriction_notice').classList.add('d-none')"></button>
                                        </div>
                                    </div>

                                    <!-- THANH TIẾN ĐỘ HỌC BÀI NÀY (ĐỒNG BỘ THỜI GIAN THỰC THEO VIDEO) -->
                                    <div class="mt-2 p-2 px-3 bg-white rounded border">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-1 gap-2">
                                            <span class="small fw-semibold text-primary" id="video_watch_text">
                                                <i class="bi bi-play-circle me-1"></i>Tiến độ học bài này: {{ $lessonPercentage }}%
                                            </span>
                                            <span class="small text-muted font-monospace" id="video_duration_text">00:00 / 00:00</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" id="video_watch_bar" role="progressbar" style="width: {{ $lessonPercentage }}%"></div>
                                        </div>
                                        <div class="small text-muted mt-1 fst-italic">
                                            * Video tiến độ bao nhiêu thì tiến độ học của bài giảng là bấy nhiêu và tự động đồng bộ sang giáo viên.
                                        </div>
                                    </div>

                                    <!-- THANH ĐIỀU KHIỂN TỐC ĐỘ PHÁT VIDEO VÀ VỊ TRÍ XEM DỞ -->
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 p-2 bg-white rounded border">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small text-muted me-2"><i class="bi bi-speedometer2 me-1"></i>Tốc độ phát:</span>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-secondary speed-btn" onclick="changePlaybackSpeed(0.75, this)">0.75x</button>
                                                <button type="button" class="btn btn-outline-secondary speed-btn active" onclick="changePlaybackSpeed(1.0, this)">1x</button>
                                                <button type="button" class="btn btn-outline-secondary speed-btn" onclick="changePlaybackSpeed(1.25, this)">1.25x</button>
                                                <button type="button" class="btn btn-outline-secondary speed-btn" onclick="changePlaybackSpeed(1.5, this)">1.5x</button>
                                                <button type="button" class="btn btn-outline-secondary speed-btn" onclick="changePlaybackSpeed(2.0, this)">2x</button>
                                            </div>
                                        </div>
                                        <div class="small text-muted" id="video_resume_indicator">
                                            @if($initialPosition > 0)
                                                <i class="bi bi-bookmark-check text-success me-1"></i>Đã khôi phục vị trí đã xem: {{ gmdate($initialPosition >= 3600 ? 'H:i:s' : 'i:s', $initialPosition) }}
                                                @if($currentProgress?->duration_seconds)
                                                    / {{ gmdate($currentProgress->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $currentProgress->duration_seconds) }} ({{ $currentProgress->watchedPercentage() }}%)
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                @elseif($videoDoc->url)
                                    <!-- TRÌNH PHÁT YOUTUBE / GOOGLE DRIVE -->
                                    <div class="ratio ratio-16x9 rounded overflow-hidden shadow-sm border bg-black">
                                        <iframe
                                            src="{{ $videoDoc->embed_url }}"
                                            title="{{ $videoDoc->title }}"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                            allowfullscreen
                                        ></iframe>
                                    </div>
                                    <div class="p-2 bg-white rounded border mt-2 small text-muted">
                                        <i class="bi bi-info-circle me-1 text-primary"></i>Video phát trực tuyến từ nền tảng liên kết. Bạn có thể bấm nút <strong>Đánh dấu hoàn thành</strong> ở trên sau khi xem xong.
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="p-5 text-center border rounded-3 bg-light text-muted mb-3">
                                <i class="bi bi-camera-video-off fs-1 d-block mb-2 text-secondary"></i>
                                <h3 class="h6 fw-semibold text-dark">Bài học này không có video bài giảng</h3>
                                <p class="small text-muted mb-0">Bạn có thể chuyển sang tab <strong>Tài liệu đính kèm</strong> để xem đề thi, tài liệu tham khảo và làm bài tập.</p>
                            </div>
                        @endif
                    </div>

                    <!-- ============================================== -->
                    <!-- TAB 2: TÀI LIỆU ĐÍNH KÈM (CLICK MỚI MỞ XEM)   -->
                    <!-- ============================================== -->
                    <div class="tab-pane fade {{ $defaultTab === 'docs' ? 'show active' : '' }}" id="st-tab-docs-pane" role="tabpanel">
                        <div class="card border rounded-3 p-3 p-md-4 shadow-2xs mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h3 class="h6 fw-bold mb-0 text-dark text-uppercase">
                                        <i class="bi bi-paperclip text-primary me-1"></i>Tệp đính kèm bài học ({{ $attachedDocs->count() }})
                                    </h3>
                                    <div class="small text-muted">Bấm vào từng tài liệu để mở xem trực tuyến hoặc tải về</div>
                                </div>
                            </div>

                            @if($attachedDocs->isNotEmpty())
                                <div class="d-flex flex-column gap-2">
                                    @foreach($attachedDocs as $doc)
                                        <div class="border rounded-3 overflow-hidden bg-white shadow-2xs">
                                            <!-- THANH FILE HEADER: CHƯA MỞ SẴN, BẤM VÀO SẼ COLLAPSE MỞ XEM -->
                                            <div class="p-3 d-flex flex-wrap align-items-center justify-content-between gap-2 bg-light">
                                                <div class="d-flex align-items-center gap-3 flex-grow-1" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#stPreviewDoc{{ $doc->id }}" aria-expanded="false" title="Bấm vào để mở hoặc đóng xem tài liệu">
                                                    <span class="fs-4"><i class="bi {{ $doc->typeIcon() }}"></i></span>
                                                    <div class="text-truncate">
                                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 420px;" title="{{ $doc->title }}">
                                                            {{ $doc->title }}
                                                        </div>
                                                        <div class="small text-muted d-flex flex-wrap align-items-center gap-2">
                                                            <span>{{ $doc->formattedSize() }}</span>
                                                            <span>•</span>
                                                            <span>{{ $doc->typeLabel() }}</span>
                                                            @if($doc->allow_view)<span class="badge bg-info-subtle text-info border">Xem online</span>@endif
                                                            @if($doc->allow_download)<span class="badge bg-success-subtle text-success border">Tải về máy</span>@endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-1">
                                                    @if($doc->allow_view)
                                                        <button class="btn btn-sm btn-outline-primary py-1 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#stPreviewDoc{{ $doc->id }}" aria-expanded="false">
                                                            <i class="bi bi-eye me-1"></i>Mở xem
                                                        </button>
                                                    @endif
                                                    @if($doc->allow_download && $doc->file_path)
                                                        <a href="{{ route('courses.documents.download', [$course, $doc]) }}" class="btn btn-sm btn-outline-success py-1 px-3" title="Tải về máy">
                                                            <i class="bi bi-download me-1"></i>Tải về
                                                        </a>
                                                    @elseif($doc->file_path)
                                                        <button class="btn btn-sm btn-outline-secondary py-1 px-2" disabled title="Tài liệu không cho phép tải về">
                                                            <i class="bi bi-lock me-1"></i>Chặn tải
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- VÙNG PREVIEW TÀI LIỆU (MẶC ĐỊNH ĐÓNG, CLICK VÀO MỚI MỞ RA) -->
                                            <div class="collapse border-top" id="stPreviewDoc{{ $doc->id }}">
                                                <div class="p-3 bg-white">
                                                    @if($doc->isPdf() && $doc->file_path)
                                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                                            <span class="small fw-semibold text-muted">Trình xem trực tuyến PDF</span>
                                                            <a href="{{ route('courses.documents.show', [$course, $doc]) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2 small">
                                                                <i class="bi bi-arrows-fullscreen me-1"></i>Toàn màn hình
                                                            </a>
                                                        </div>
                                                        <iframe src="{{ route('courses.documents.show', [$course, $doc]) }}#toolbar=1" class="w-100 rounded border" style="height: 540px;" title="{{ $doc->title }}"></iframe>
                                                    @elseif($doc->isOffice() && $doc->file_path)
                                                        <div class="text-center p-4 bg-light rounded border">
                                                            <i class="bi {{ $doc->typeIcon() }} display-4 mb-2 text-secondary"></i>
                                                            <h4 class="h6 fw-bold mb-1">{{ $doc->original_name }}</h4>
                                                            <p class="small text-muted mb-3">Tệp tài liệu văn phòng ({{ $doc->formattedSize() }}).</p>
                                                            <div class="d-flex justify-content-center gap-2">
                                                                <a href="{{ route('courses.documents.show', [$course, $doc]) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                    <i class="bi bi-eye me-1"></i>Xem tệp trực tuyến
                                                                </a>
                                                                @if($doc->allow_download)
                                                                    <a href="{{ route('courses.documents.download', [$course, $doc]) }}" class="btn btn-sm btn-success">
                                                                        <i class="bi bi-download me-1"></i>Tải về máy tính
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @elseif($doc->isLink())
                                                        <div class="text-center p-4 bg-light rounded border">
                                                            <i class="bi bi-link-45deg display-4 mb-2 text-info"></i>
                                                            <h4 class="h6 fw-bold mb-1">{{ $doc->title }}</h4>
                                                            <p class="small text-muted mb-3 text-truncate">{{ $doc->url }}</p>
                                                            <a href="{{ $doc->url }}" target="_blank" class="btn btn-sm btn-primary">
                                                                <i class="bi bi-box-arrow-up-right me-1"></i>Mở liên kết học liệu
                                                            </a>
                                                        </div>
                                                    @else
                                                        <div class="text-center p-3 text-muted small">
                                                            Tệp đính kèm: <strong>{{ $doc->original_name }}</strong> ({{ $doc->formattedSize() }})
                                                            @if($doc->allow_download)
                                                                <a href="{{ route('courses.documents.download', [$course, $doc]) }}" class="ms-2 btn btn-sm btn-outline-success">Tải về</a>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-4 text-center border rounded-3 bg-light text-muted">
                                    <i class="bi bi-paperclip fs-1 d-block mb-2 text-secondary"></i>
                                    <h4 class="h6 fw-semibold text-dark">Bài học chưa có tệp đính kèm</h4>
                                    <p class="small text-muted mb-0">Khi giảng viên tải lên bài tập hoặc tài liệu tham khảo, các tài liệu sẽ xuất hiện tại đây.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- ĐIỀU HƯỚNG BÀI TRƯỚC / TIẾP THEO -->
                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                    @if($prevLesson)
                        <a href="{{ route('student.courses.learn', [$course, $prevLesson->id]) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i>Bài trước: {{ Str::limit($prevLesson->title, 20) }}
                        </a>
                    @else
                        <button class="btn btn-outline-secondary btn-sm" disabled>← Bài trước</button>
                    @endif

                    @if($nextLesson)
                        <a href="{{ route('student.courses.learn', [$course, $nextLesson->id]) }}" class="btn btn-primary btn-sm">
                            Bài tiếp theo: {{ Str::limit($nextLesson->title, 20) }}<i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    @else
                        <span class="text-success small fw-semibold"><i class="bi bi-flag-fill me-1"></i>Bạn đã xem đến bài cuối cùng</span>
                    @endif
                </div>
            @endif
        </div>

        <!-- CỘT PHẢI / MỤC LỤC CHƯƠNG BÀI (CURRICULUM SIDEBAR) -->
        <div class="col-lg-4 col-xl-3 d-none d-lg-block bg-light p-3" style="max-height: 800px; overflow-y: auto;">
            <div class="fw-bold small text-uppercase text-muted mb-3">
                <i class="bi bi-list-nested me-1 text-primary"></i>Mục lục khóa học
            </div>
            <div class="accordion curriculum-nav" id="sidebarCurriculumAccordion">
                @foreach($chapters as $chIndex => $ch)
                    @php
                        $hasActiveLesson = $ch->lessons->contains('id', $currentLesson?->id);
                    @endphp
                    <div class="accordion-item border rounded mb-2 overflow-hidden bg-white shadow-2xs">
                        <h3 class="accordion-header" id="headingCh{{ $ch->id }}">
                            <button class="accordion-button p-2 px-3 bg-light text-dark fw-semibold small {{ $hasActiveLesson ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCh{{ $ch->id }}">
                                <span class="badge bg-secondary me-2">{{ $chIndex + 1 }}</span>
                                <span class="text-truncate">{{ $ch->title }}</span>
                            </button>
                        </h3>
                        <div id="collapseCh{{ $ch->id }}" class="accordion-collapse collapse {{ $hasActiveLesson ? 'show' : '' }}">
                            <div class="accordion-body p-1">
                                <div class="list-group list-group-flush small">
                                    @foreach($ch->lessons as $lesIndex => $les)
                                        @php
                                            $isCompleted = in_array($les->id, $completedLessonIds);
                                            $isActive = $currentLesson?->id === $les->id;
                                            $hasLesVideo = $les->documents->contains(fn ($d) => $d->isVideo());
                                            $lesPct = $les->progressPercentageFor($user);
                                        @endphp
                                        <a
                                            href="{{ route('student.courses.learn', [$course, $les->id]) }}"
                                            class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-2 rounded mb-1 border-0 {{ $isActive ? 'bg-primary-subtle text-primary fw-bold border-start border-3 border-primary' : 'text-dark' }}"
                                        >
                                            <div class="d-flex align-items-center text-truncate me-2">
                                                @if($isCompleted || $lesPct >= 100)
                                                    <i class="bi bi-check-circle-fill text-success me-2 fs-6"></i>
                                                @elseif($hasLesVideo && $lesPct > 0)
                                                    <i class="bi bi-play-circle-fill text-warning me-2 fs-6"></i>
                                                @elseif($hasLesVideo)
                                                    <i class="bi bi-play-circle text-danger me-2 fs-6"></i>
                                                @else
                                                    <i class="bi bi-circle text-muted me-2 fs-6"></i>
                                                @endif
                                                <span class="text-truncate">{{ $chIndex + 1 }}.{{ $lesIndex + 1 }} {{ $les->title }}</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                                @if($isCompleted || $lesPct >= 100)
                                                    <span class="badge bg-success-subtle text-success border border-success" style="font-size: 0.7rem;">100%</span>
                                                @elseif($lesPct > 0)
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning" style="font-size: 0.7rem;">{{ $lesPct }}%</span>
                                                @endif
                                                <span class="badge bg-light text-muted border small">{{ $les->documents->count() }}</span>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- OFFCANVAS CHO THIẾT BỊ DI ĐỘNG -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="curriculumDrawer">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title h6 fw-bold">Mục lục khóa học</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        @foreach($chapters as $chIndex => $ch)
            <div class="mb-3">
                <div class="fw-bold small text-muted mb-2">Chương {{ $chIndex + 1 }}: {{ $ch->title }}</div>
                <div class="list-group list-group-flush small">
                    @foreach($ch->lessons as $lesIndex => $les)
                        @php
                            $isCompleted = in_array($les->id, $completedLessonIds);
                            $isActive = $currentLesson?->id === $les->id;
                            $hasLesVideo = $les->documents->contains(fn ($d) => $d->isVideo());
                            $lesPct = $les->progressPercentageFor($user);
                        @endphp
                        <a href="{{ route('student.courses.learn', [$course, $les->id]) }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-2 {{ $isActive ? 'active' : '' }}">
                            <span>
                                <i class="bi {{ $isCompleted || $lesPct >= 100 ? 'bi-check-circle-fill text-success' : ($hasLesVideo && $lesPct > 0 ? 'bi-play-circle-fill text-warning' : ($hasLesVideo ? 'bi-play-circle text-danger' : 'bi-circle text-muted')) }} me-2"></i>
                                {{ $chIndex + 1 }}.{{ $lesIndex + 1 }} {{ $les->title }}
                            </span>
                            <div class="d-flex align-items-center gap-1">
                                @if($isCompleted || $lesPct >= 100)
                                    <span class="badge bg-success small">100%</span>
                                @elseif($lesPct > 0)
                                    <span class="badge bg-warning text-dark small">{{ $lesPct }}%</span>
                                @endif
                                <span class="badge bg-secondary">{{ $les->documents->count() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
// ĐIỀU CHỈNH TỐC ĐỘ PHÁT VIDEO
function changePlaybackSpeed(speed, btn) {
    const video = document.getElementById('lessonVideoPlayer');
    if (video) {
        video.playbackRate = speed;
        document.querySelectorAll('.speed-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }
}

// XỬ LÝ LƯU VÀ THEO DÕI TIẾN ĐỘ XEM VIDEO THỜI GIAN THỰC (TIẾN ĐỘ VIDEO = TIẾN ĐỘ BÀI HỌC)
document.addEventListener('DOMContentLoaded', function () {
    const video = document.getElementById('lessonVideoPlayer');
    if (!video) return;

    let isCompleted = {{ $isCurrentLessonCompleted ? 'true' : 'false' }};
    const saveUrl = video.dataset.saveUrl;
    const initialPos = parseInt(video.dataset.initialPosition || '0', 10);
    const csrfToken = '{{ csrf_token() }}';

    // Thời lượng xem lớn nhất (để không bị tụt tiến độ khi tua lùi và kiểm soát tua tới)
    let maxTimeWatched = Math.max(initialPos, 0);

    const watchBar = document.getElementById('video_watch_bar');
    const watchText = document.getElementById('video_watch_text');
    const durText = document.getElementById('video_duration_text');
    const statusBadge = document.getElementById('lesson_status_badge');
    const statusText = document.getElementById('lesson_status_text');
    const coursePercentText = document.getElementById('course_percent_text');
    const courseProgressBar = document.getElementById('course_progress_bar');
    const seekNotice = document.getElementById('seek_restriction_notice');

    let seekNoticeTimer = null;
    function showSeekNotice() {
        if (!seekNotice) return;
        seekNotice.classList.remove('d-none');
        clearTimeout(seekNoticeTimer);
        seekNoticeTimer = setTimeout(() => {
            seekNotice.classList.add('d-none');
        }, 4000);
    }

    function formatTime(secs) {
        secs = Math.floor(secs || 0);
        const m = Math.floor(secs / 60);
        const s = secs % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    function updateProgressUI(pct, currentSecs, totalSecs) {
        if (watchBar) watchBar.style.width = pct + '%';
        if (watchText) watchText.innerHTML = '<i class="bi bi-play-circle me-1"></i>Tiến độ học bài này: ' + pct + '%';
        if (durText && totalSecs > 0) {
            durText.innerText = formatTime(currentSecs) + ' / ' + formatTime(totalSecs);
        }
        if (!isCompleted && statusText) {
            statusText.innerText = 'Tiến độ: ' + pct + '%';
            if (statusBadge) statusBadge.className = 'badge bg-primary p-2';
        }
    }

    // Khi video tải xong metadata: khôi phục vị trí, cập nhật hiển thị tổng thời lượng và đồng bộ lên server ngay
    function onMetadataLoaded() {
        const dur = Math.floor(video.duration || 0);
        if (initialPos > 0 && dur > 0 && initialPos < dur) {
            video.currentTime = initialPos;
        }
        if (dur > 0) {
            const currentPct = Math.min(100, Math.round((maxTimeWatched / dur) * 100));
            updateProgressUI(currentPct, video.currentTime, dur);
            // Đồng bộ ngay thời lượng lên server để DB luôn có duration_seconds
            saveVideoProgress(false);
        }
    }

    video.addEventListener('loadedmetadata', onMetadataLoaded, { once: true });
    if (video.readyState >= 1) {
        onMetadataLoaded();
    }

    // GIỚI HẠN TUA:
    // - Cho tua lùi tự do (currentTime <= maxTimeWatched).
    // - Tua tới bị giới hạn: không được vượt quá maxTimeWatched (nếu bài chưa hoàn thành).
    // - Bài đã hoàn thành: được tua tự do cả tiến và lùi.
    video.addEventListener('seeking', function () {
        if (isCompleted) return;

        // Nếu cố tua tới vượt quá thời lượng đã xem lớn nhất (+ 1.5 giây dung sai)
        if (video.currentTime > maxTimeWatched + 1.5) {
            video.currentTime = maxTimeWatched;
            showSeekNotice();
        }
    });

    // Cập nhật tiến độ theo thời gian thực (lấy thời lượng xem lớn nhất)
    video.addEventListener('timeupdate', function () {
        if (!video.duration) return;

        // Kiểm tra an toàn tua tới trong lúc phát
        if (!isCompleted && video.currentTime > maxTimeWatched + 2.0) {
            video.currentTime = maxTimeWatched;
            showSeekNotice();
            return;
        }

        // Cập nhật mốc xem lớn nhất (không bao giờ giảm khi tua lùi ôn lại bài)
        if (video.currentTime > maxTimeWatched) {
            maxTimeWatched = video.currentTime;
        }

        const percent = Math.min(100, Math.round((maxTimeWatched / video.duration) * 100));
        updateProgressUI(percent, video.currentTime, video.duration);
    });

    // Gửi tiến độ lên server (luôn gửi maxTimeWatched để database lưu mốc cao nhất)
    let lastSavedTime = 0;
    function saveVideoProgress(isEnded = false) {
        const sendSeconds = Math.floor(maxTimeWatched);
        const duration = Math.floor(video.duration || 0);

        if (!isEnded && Math.abs(sendSeconds - lastSavedTime) < 3 && duration > 0 && lastSavedTime > 0) {
            return;
        }
        lastSavedTime = sendSeconds;

        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                seconds: sendSeconds,
                duration: duration,
                is_ended: isEnded
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.is_completed) {
                isCompleted = true;
                if (statusBadge) statusBadge.className = 'badge bg-success p-2';
                if (statusText) statusText.innerText = 'Đã hoàn thành (100%)';
                if (watchBar) watchBar.style.width = '100%';
                if (watchText) watchText.innerHTML = '<i class="bi bi-check-circle-fill me-1 text-success"></i>Tiến độ học bài này: 100%';
                if (seekNotice) seekNotice.classList.add('d-none');
            }
            if (data.percentage !== undefined) {
                if (coursePercentText) coursePercentText.innerText = data.percentage + '%';
                if (courseProgressBar) {
                    courseProgressBar.style.width = data.percentage + '%';
                    courseProgressBar.setAttribute('aria-valuenow', data.percentage);
                }
            }
        })
        .catch(err => console.error('Lỗi lưu tiến độ video:', err));
    }

    let intervalId = null;
    video.addEventListener('play', () => {
        intervalId = setInterval(() => saveVideoProgress(false), 5000);
    });
    video.addEventListener('pause', () => {
        if (intervalId) clearInterval(intervalId);
        saveVideoProgress(false);
    });
    video.addEventListener('ended', () => {
        if (intervalId) clearInterval(intervalId);
        saveVideoProgress(true);
    });
});
</script>
@endsection
