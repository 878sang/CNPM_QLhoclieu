@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')

@section('content')
@php
$allLessons = $course->chapters->pluck('lessons')->flatten();
$activeLessonId = request('lesson_id');
$currentLesson = $activeLessonId ? $allLessons->firstWhere('id', $activeLessonId) : $allLessons->first();
$activeTab = request('tab', 'content');
$pendingMaterialCount = $currentLesson?->documents->where('status', 'pending')->count() ?? 0;
@endphp

@if (session('status'))
<x-alert tone="success" class="mb-3">{{ session('status') }}</x-alert>
@endif

<!-- ============================================================ -->
<!-- KHUNG STUDIO TỔNG THỂ LIỀN MẠCH, KHÔNG BỊ LỖ PADDING         -->
<!-- ============================================================ -->
<div class="card border rounded-3 overflow-hidden shadow-sm bg-white mb-4">
    <!-- 1. THANH HEADER TRÊN CÙNG MÀU XANH DƯƠNG CHUẨN THEO MẪU -->
    <div class="bg-primary text-white py-2 px-3 px-md-4 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('courses.index') }}" class="btn btn-sm btn-outline-light border-0 fw-semibold text-white px-2">
                <i class="bi bi-arrow-left me-1"></i> Trở lại
            </a>
            <div class="vr bg-white opacity-25 d-none d-sm-block" style="height: 20px;"></div>
            <span class="badge bg-white text-primary fw-bold d-none d-sm-inline-block">{{ $course->subject?->name }}</span>
            <span class="badge {{ $course->status === 'public' ? 'bg-success' : 'bg-warning text-dark' }} fw-bold d-none d-md-inline-block">
                {{ $course->status === 'public' ? 'Khóa học công khai' : 'Khóa học bản nháp' }}
            </span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('courses.assignments.index', $course) }}" class="btn btn-sm btn-light text-primary fw-semibold">
                <i class="bi bi-journal-check me-1"></i>Quản lý bài tập
            </a>
            <div class="h6 mb-0 text-white fw-bold text-truncate text-end" style="max-width: 500px;">
                {{ $course->title }}
            </div>
        </div>
    </div>

    <!-- 2. THANH SUB-NAVIGATION TABS (THÔNG TIN, NỘI DUNG HỌC, HỌC VIÊN, TIẾN ĐỘ) -->
    <div class="bg-white border-bottom px-3 px-md-4">
        <ul class="nav nav-underline flex-nowrap overflow-auto" id="courseStudioTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link py-3 px-3 fw-semibold text-uppercase {{ $activeTab === 'info' ? 'active' : '' }}" href="{{ route('courses.show', ['course' => $course, 'tab' => 'info']) }}">
                    <i class="bi bi-info-circle me-1"></i>THÔNG TIN
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-3 px-3 fw-semibold text-uppercase {{ $activeTab === 'content' ? 'active' : '' }}" href="{{ route('courses.show', ['course' => $course, 'lesson_id' => $currentLesson?->id, 'tab' => 'content']) }}">
                    <i class="bi bi-book-half me-1"></i>NỘI DUNG HỌC
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-3 px-3 fw-semibold text-uppercase {{ $activeTab === 'students' ? 'active' : '' }}" href="{{ route('courses.show', ['course' => $course, 'tab' => 'students']) }}">
                    <i class="bi bi-people me-1"></i>HỌC VIÊN ({{ $course->enrollments->count() }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-3 px-3 fw-semibold text-uppercase {{ $activeTab === 'progress' ? 'active' : '' }}" href="{{ route('courses.show', ['course' => $course, 'tab' => 'progress']) }}">
                    <i class="bi bi-bar-chart-line me-1"></i>TIẾN ĐỘ HỌC
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-3 px-3 fw-semibold text-uppercase {{ $activeTab === 'documents' ? 'active' : '' }}" href="{{ route('courses.show', ['course' => $course, 'tab' => 'documents']) }}">
                    <i class="bi bi-folder2-open me-1"></i>TÀI LIỆU ({{ $course->documents->count() }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-3 px-3 fw-semibold text-uppercase" href="{{ route('courses.assignments.index', $course) }}">
                    <i class="bi bi-journals me-1"></i>BÀI TẬP ({{ $course->assignments->count() }})
                </a>
            </li>
        </ul>
    </div>

    @if($activeTab === 'content')
    <!-- ============================================================ -->
    <!-- TAB NỘI DUNG HỌC: BỐ CỤC 2 CỘT CHUẨN THEO ẢNH MẪU            -->
    <!-- ============================================================ -->
    <div class="row g-0">
        <!-- CỘT TRÁI: SIDEBAR CÂY CHƯƠNG & BÀI HỌC -->
        <div class="col-lg-4 col-xl-3 border-end bg-light d-flex flex-column" style="min-height: 720px;">
            <!-- Header Sidebar -->
            <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-white">
                <div class="fw-bold text-dark small text-uppercase">
                    <i class="bi bi-list-ul text-primary me-1"></i>Mục lục bài giảng
                </div>
                <span class="badge bg-secondary-subtle text-secondary border">
                    {{ $allLessons->count() }} bài
                </span>
            </div>

            <!-- Danh sách cây chương và bài học -->
            <div class="flex-grow-1 overflow-auto p-2" style="max-height: 720px;">
                @forelse($course->chapters as $cIndex => $chapter)
                <div class="mb-3 border rounded bg-white shadow-2xs overflow-hidden">
                    <!-- Tiêu đề chương -->
                    <div class="p-2 px-3 bg-light d-flex align-items-center justify-content-between border-bottom">
                        <div class="fw-bold small text-dark d-flex align-items-center gap-1 text-truncate" title="{{ $chapter->title }}">
                            <i class="bi bi-folder2-open text-warning me-1"></i>
                            {{ $chapter->title }}
                        </div>
                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                            <button class="btn btn-sm btn-link text-secondary p-0 px-1" type="button" data-bs-toggle="collapse" data-bs-target="#editChapterCollapse{{ $chapter->id }}" title="Đổi tên chương">
                                <i class="bi bi-pencil small"></i>
                            </button>
                            <form method="POST" action="{{ route('courses.chapters.destroy', [$course, $chapter]) }}" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa chương này?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0 px-1" title="Xóa chương">
                                    <i class="bi bi-trash small"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Collapse sửa tên chương -->
                    <div class="collapse p-2 bg-light border-bottom" id="editChapterCollapse{{ $chapter->id }}">
                        <form method="POST" action="{{ route('courses.chapters.update', [$course, $chapter]) }}" class="d-flex gap-1">
                            @csrf
                            @method('PUT')
                            <input type="text" name="title" value="{{ $chapter->title }}" class="form-control form-control-sm" required>
                            <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
                        </form>
                    </div>

                    <!-- Danh sách bài học của chương -->
                    <div class="list-group list-group-flush">
                        @forelse($chapter->lessons as $lIndex => $lesson)
                        @php
                        $hasVideo = $lesson->documents->contains(fn ($d) => $d->isVideo());
                        $isLessonActive = $currentLesson && $currentLesson->id === $lesson->id;
                        @endphp
                        <a href="{{ route('courses.show', ['course' => $course, 'lesson_id' => $lesson->id, 'tab' => 'content']) }}"
                            class="list-group-item list-group-item-action py-2 px-3 d-flex align-items-center justify-content-between border-0 {{ $isLessonActive ? 'bg-primary-subtle text-primary fw-bold border-start border-3 border-primary' : 'text-dark' }}">
                            <div class="d-flex align-items-center gap-2 text-truncate me-2">
                                <i class="bi {{ $hasVideo ? 'bi-play-btn-fill text-danger' : 'bi-file-earmark-text text-secondary' }} fs-6"></i>
                                <span class="small text-truncate" title="{{ $lesson->title }}">{{ $lesson->title }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                @if($lesson->isDraft())
                                <span class="badge bg-warning-subtle text-warning border small fw-normal">Nháp</span>
                                @elseif($lesson->isPending())
                                <span class="badge bg-warning-subtle text-warning border small fw-normal">Chờ duyệt</span>
                                @endif
                                <span class="badge bg-light text-muted border small fw-normal">
                                    {{ $lesson->documents->count() }}
                                </span>
                            </div>
                        </a>
                        @empty
                        <div class="p-2 text-muted text-center small fst-italic">
                            Chưa có bài giảng nào
                        </div>
                        @endforelse
                    </div>

                    <!-- Nút thao tác trong chương (+ Bài giảng) -->
                    <div class="p-2 bg-light border-top d-flex align-items-center gap-1">
                        <button class="btn btn-sm btn-outline-primary py-1 px-2 flex-grow-1" type="button" data-bs-toggle="collapse" data-bs-target="#addLessonCollapse{{ $chapter->id }}">
                            <i class="bi bi-plus-lg me-1"></i>Bài giảng
                        </button>
                        <a href="{{ route('courses.documents.index', ['course' => $course]) }}" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Quản lý toàn bộ tài liệu">
                            <i class="bi bi-files"></i>
                        </a>
                    </div>

                    <!-- Collapse thêm bài học mới -->
                    <div class="collapse p-2 bg-white border-top" id="addLessonCollapse{{ $chapter->id }}">
                        <form method="POST" action="{{ route('courses.chapters.lessons.store', [$course, $chapter]) }}">
                            @csrf
                            <input type="text" name="title" class="form-control form-control-sm mb-2" placeholder="Tên bài học (VD: Buổi 1: Bài toán...)" required>
                            <div class="d-flex justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" data-bs-toggle="collapse" data-bs-target="#addLessonCollapse{{ $chapter->id }}">Hủy</button>
                                <button type="submit" class="btn btn-sm btn-primary py-0 px-2">Thêm</button>
                            </div>
                        </form>
                    </div>
                </div>
                @empty
                <div class="text-center p-4 text-muted small">
                    Chưa có chương học nào. Hãy bấm nút phía dưới để tạo chương đầu tiên.
                </div>
                @endforelse
            </div>

            <!-- Footer Sidebar: Nút thêm chương mới -->
            <div class="p-3 border-top bg-white">
                <button class="btn btn-primary btn-sm w-100 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#addChapterCollapse">
                    <i class="bi bi-plus-circle me-1"></i>Thêm chương mới
                </button>
                <div class="collapse mt-2" id="addChapterCollapse">
                    <form method="POST" action="{{ route('courses.chapters.store', $course) }}" class="p-2 border rounded bg-light">
                        @csrf
                        <input type="text" name="title" class="form-control form-control-sm mb-2" placeholder="VD: Chương 4: Xác suất" required>
                        <div class="d-flex justify-content-end gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" data-bs-toggle="collapse" data-bs-target="#addChapterCollapse">Hủy</button>
                            <button type="submit" class="btn btn-sm btn-primary py-0 px-2">Lưu chương</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- CỘT PHẢI: CHI TIẾT BÀI GIẢNG VỚI 2 TAB (VIDEO & TÀI LIỆU ĐÍNH KÈM) -->
        <div class="col-lg-8 col-xl-9 p-3 p-md-4 bg-white">
            @if($currentLesson)
            <!-- Tiêu đề bài học lớn phía trên cùng trạng thái -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                <div>
                    <div class="small text-muted mb-1">
                        <i class="bi bi-folder me-1 text-warning"></i>{{ $currentLesson->chapter?->title }}
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <h2 class="h4 mb-0 fw-bold text-dark">{{ $currentLesson->title }}</h2>
                        @if($currentLesson->isDraft())
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning">
                            <i class="bi bi-pencil-square me-1"></i>Bản nháp
                        </span>
                        @elseif($currentLesson->isPending())
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning">
                            <i class="bi bi-hourglass-split me-1"></i>Đang chờ duyệt
                        </span>
                        @else
                        <span class="badge bg-success-subtle text-success border border-success">
                            <i class="bi bi-check-circle-fill me-1"></i>Đã xuất bản
                        </span>
                        @endif
                        @if($pendingMaterialCount > 0)
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning" title="Tài liệu mới hoặc cập nhật đang chờ Admin duyệt">
                            <i class="bi bi-hourglass-split me-1"></i>{{ $pendingMaterialCount }} học liệu chờ duyệt
                        </span>
                        @endif
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <form method="POST" action="{{ route('courses.chapters.lessons.destroy', [$course, $currentLesson->chapter, $currentLesson]) }}" onsubmit="return confirm('Bạn có chắc muốn xóa bài giảng này?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash me-1"></i>Xóa bài giảng
                        </button>
                    </form>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('courses.assignments.create', ['course' => $course, 'lesson_id' => $currentLesson->id]) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-circle me-1"></i>Tạo bài tập cho bài này
                    </a>
                    <a href="{{ route('courses.assignments.index', $course) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-journal-check me-1"></i>Quản lý bài tập
                    </a>
                </div>
            </div>

            <!-- KHUNG THÔNG TIN BÀI GIẢNG (HIỆN NÚT CẬP NHẬT KHI CÓ THAY ĐỔI) -->
            <div class="card border rounded-3 p-3 bg-white mb-3 shadow-2xs">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h3 class="h6 fw-bold mb-0 text-dark">
                        <i class="bi bi-info-circle text-primary me-1"></i>Thông tin bài giảng
                    </h3>
                    <div class="d-flex align-items-center gap-2">
                        @if($currentLesson->isDraft())
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning">
                            <i class="bi bi-pencil-square me-1"></i>Bản nháp
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-success py-1 px-2" onclick="setLessonStatus('pending')">
                            <i class="bi bi-send-check me-1"></i>Gửi duyệt bài học
                        </button>
                        @elseif($currentLesson->isPending())
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning"><i class="bi bi-hourglass-split me-1"></i>Đang chờ duyệt</span>
                        @else
                        <span class="badge bg-success-subtle text-success border border-success">
                            <i class="bi bi-check-circle-fill me-1"></i>Đã xuất bản
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 d-none" id="lesson_resubmit_btn" onclick="cancelLessonEdit()" title="Hủy thay đổi tên bài học">
                            <i class="bi bi-x-lg me-1"></i>Hủy thay đổi
                        </button>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('courses.chapters.lessons.update', [$course, $currentLesson->chapter, $currentLesson]) }}" id="lesson_edit_form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="status" id="lesson_status_input" value="{{ $currentLesson->status ?? 'draft' }}">

                    <div>
                        <label for="lesson_title_input" class="form-label small text-muted mb-1">Tên bài giảng</label>
                        <div class="input-group">
                            <input
                                type="text"
                                name="title"
                                id="lesson_title_input"
                                class="form-control"
                                value="{{ $currentLesson->title }}"
                                data-original="{{ $currentLesson->title }}"
                                placeholder="Nhập tên bài giảng..."
                                required
                            >
                            @if(!$currentLesson->isDraft())
                                <button type="submit" class="btn btn-primary px-3 fw-semibold d-none" id="lesson_update_btn">
                                    <i class="bi bi-send-check me-1"></i>Lưu và gửi duyệt
                                </button>
                            @endif
                        </div>
                        <div class="form-text small text-muted">Chỉnh sửa tên bài giảng và nút Cập nhật sẽ tự động xuất hiện.</div>
                    </div>
                </form>
            </div>

            @php
            $videoDoc = $currentLesson->documents->first(fn ($d) => $d->isVideo());
            $attachedDocs = $currentLesson->documents->filter(fn ($d) => !$d->isVideo());
            @endphp

            <!-- 2 TAB RÕ RÀNG THEO YÊU CẦU: TAB VIDEO & TAB TÀI LIỆU ĐÍNH KÈM -->
            <ul class="nav nav-pills nav-fill bg-light p-1 rounded-3 mb-3 border" id="lessonStudioTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold py-2" id="tab-video-head" data-bs-toggle="pill" data-bs-target="#tab-video-pane" type="button" role="tab" aria-selected="true">
                        <i class="bi bi-camera-video me-1 text-danger"></i>Video bài giảng
                        @if($videoDoc)
                        <span class="badge bg-danger ms-1 text-white">1</span>
                        @else
                        <span class="badge bg-secondary-subtle text-secondary ms-1">0</span>
                        @endif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold py-2" id="tab-docs-head" data-bs-toggle="pill" data-bs-target="#tab-docs-pane" type="button" role="tab" aria-selected="false">
                        <i class="bi bi-paperclip me-1 text-primary"></i>Tài liệu đính kèm
                        <span class="badge bg-primary ms-1 text-white">{{ $attachedDocs->count() }}</span>
                    </button>
                </li>
            </ul>

            <!-- NỘI DUNG 2 TAB -->
            <div class="tab-content" id="lessonStudioTabsContent">
                <!-- ============================================== -->
                <!-- TAB 1: VIDEO BÀI GIẢNG                         -->
                <!-- ============================================== -->
                <div class="tab-pane fade show active" id="tab-video-pane" role="tabpanel" aria-labelledby="tab-video-head">
                    <div class="card border rounded-3 p-3 p-md-4 shadow-2xs">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h6 fw-bold mb-0 text-dark text-uppercase">
                                <i class="bi bi-camera-video text-danger me-1"></i>Video bài giảng
                            </h3>
                            @if(!$videoDoc)
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddVideo">
                                <i class="bi bi-cloud-arrow-up me-1"></i>Thêm video bài giảng
                            </button>
                            @endif
                        </div>

                        @if($videoDoc)
                        <!-- Khung hiển thị video bài giảng -->
                        <div class="mb-3">
                            <div class="card border rounded-3 overflow-hidden" style="max-width: 580px;">
                                <div class="position-relative bg-dark d-flex align-items-center justify-content-center" style="height: 260px;">
                                    @if($videoDoc->isYouTube() || $videoDoc->isGoogleDrive())
                                    <iframe src="{{ $videoDoc->embed_url }}" class="w-100 h-100" frameborder="0" allowfullscreen></iframe>
                                    @elseif($videoDoc->file_path)
                                    <video controls class="w-100 h-100" style="object-fit: contain;">
                                        <source src="{{ route('courses.documents.show', [$course, $videoDoc]) }}" type="{{ $videoDoc->mime_type ?: 'video/mp4' }}">
                                    </video>
                                    @else
                                    <div class="text-white text-center">
                                        <i class="bi bi-play-circle fs-1 text-danger"></i>
                                        <div class="small mt-1">Video bài giảng</div>
                                    </div>
                                    @endif
                                    <span class="position-absolute bottom-0 end-0 m-2 badge bg-dark text-white opacity-75">
                                        {{ $videoDoc->formattedSize() }}
                                    </span>
                                </div>
                                <div class="p-3 bg-light d-flex align-items-center justify-content-between">
                                    <div class="text-truncate me-2">
                                        <div class="fw-semibold small text-truncate" title="{{ $videoDoc->title }}">{{ $videoDoc->title }}</div>
                                        <div class="small text-muted">{{ $videoDoc->original_name ?: 'Video trực tuyến' }}</div>
                                        @if($videoDoc->isPending())
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning mt-1"><i class="bi bi-hourglass-split me-1"></i>Chờ Admin duyệt</span>
                                        @elseif($videoDoc->isRejected())
                                        <span class="badge bg-danger-subtle text-danger border border-danger mt-1"><i class="bi bi-x-circle me-1"></i>Bị từ chối</span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        @if($videoDoc->isPending())
                                        <button type="button" class="btn btn-sm btn-warning py-1 px-2" disabled title="Video đã được gửi và đang chờ Admin duyệt">
                                            <i class="bi bi-hourglass-split me-1"></i>Đang chờ duyệt
                                        </button>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" data-bs-toggle="modal" data-bs-target="#editDocumentModal{{ $videoDoc->id }}" title="Đổi hoặc cập nhật video">
                                            <i class="bi bi-pencil me-1"></i>Đổi video
                                        </button>
                                        <form method="POST" action="{{ route('courses.documents.destroy', [$course, $videoDoc]) }}" onsubmit="return confirm('Bạn có chắc muốn xóa video này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Xóa video">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="p-4 text-center border rounded-3 bg-light text-muted mb-3">
                            <i class="bi bi-camera-video fs-1 d-block mb-2 text-secondary"></i>
                            <h4 class="h6 fw-semibold text-dark mb-1">Chưa có video cho bài giảng này</h4>
                            <p class="small text-muted mb-3">Tải lên video bài giảng MP4 (tối đa 500 MB) hoặc dán link YouTube / Google Drive.</p>
                            <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalAddVideo">
                                <i class="bi bi-cloud-arrow-up me-1"></i>Tải lên hoặc dán link video
                            </button>
                        </div>
                        @endif

                        @if($videoDoc)
                            @include('documents._edit-modal', ['document' => $videoDoc])
                        @endif

                        <!-- Tùy chọn video -->
                        <div class="mt-2 pt-2 border-top">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="check_video_url" data-bs-toggle="modal" data-bs-target="#modalAddVideo">
                                <label class="form-check-label text-dark small" for="check_video_url">
                                    Sử dụng đường dẫn video có sẵn (YouTube, Google Drive, v.v.)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="check_disable_seek" checked>
                                <label class="form-check-label text-dark small" for="check_disable_seek">
                                    Không cho phép tua video (theo dõi tiến độ học tập bắt buộc)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- TAB 2: TÀI LIỆU ĐÍNH KÈM (CLICK MỚI MỞ XEM)   -->
                <!-- ============================================== -->
                <div class="tab-pane fade" id="tab-docs-pane" role="tabpanel" aria-labelledby="tab-docs-head">
                    <div class="card border rounded-3 p-3 p-md-4 shadow-2xs">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <h3 class="h6 fw-bold mb-0 text-dark text-uppercase">
                                    <i class="bi bi-paperclip text-primary me-1"></i>Tệp đính kèm bài học
                                </h3>
                                <div class="small text-muted">Tài liệu, đề thi, bài tập (chưa mở sẵn - bấm vào từng tệp để mở xem)</div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalUploadDocument">
                                <i class="bi bi-upload me-1"></i>Tải lên tài liệu
                            </button>
                        </div>

                        @if($attachedDocs->isNotEmpty())
                        <div class="d-flex flex-column gap-2">
                            @foreach($attachedDocs as $doc)
                            <div class="border rounded-3 overflow-hidden bg-white shadow-2xs">
                                <!-- THANH ITEM TÀI LIỆU: MẶC ĐỊNH CHƯA MỞ, CLICK VÀO ĐỂ XEM -->
                                <div class="p-3 d-flex flex-wrap align-items-center justify-content-between gap-2 bg-light">
                                    <div class="d-flex align-items-center gap-3 flex-grow-1" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#previewDocCollapse{{ $doc->id }}" aria-expanded="false" title="Bấm vào để mở/đóng xem tài liệu này">
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
                                                @if($doc->allow_download)<span class="badge bg-success-subtle text-success border">Tải về</span>@endif
                                                @if($doc->isPending())<span class="badge bg-warning-subtle text-warning-emphasis border border-warning"><i class="bi bi-hourglass-split me-1"></i>Chờ Admin duyệt</span>@elseif($doc->isRejected())<span class="badge bg-danger-subtle text-danger border border-danger"><i class="bi bi-x-circle me-1"></i>Bị từ chối</span>@endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        @if($doc->isPending())
                                        <button type="button" class="btn btn-sm btn-warning py-1 px-2" disabled title="Tài liệu đã được gửi và đang chờ Admin duyệt">
                                            <i class="bi bi-hourglass-split me-1"></i>Đang chờ duyệt
                                        </button>
                                        @endif
                                        <button class="btn btn-sm btn-outline-primary py-1 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#previewDocCollapse{{ $doc->id }}" aria-expanded="false" title="Mở xem tài liệu">
                                            <i class="bi bi-eye me-1"></i>Xem
                                        </button>
                                        @if($doc->allow_download)
                                        <a href="{{ route('courses.documents.download', [$course, $doc]) }}" class="btn btn-sm btn-outline-success py-1 px-2" title="Tải về máy">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" data-bs-toggle="modal" data-bs-target="#editDocumentModal{{ $doc->id }}" title="Chỉnh sửa">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="{{ route('courses.documents.destroy', [$course, $doc]) }}" onsubmit="return confirm('Bạn có chắc muốn xóa tài liệu này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Xóa tài liệu">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- KHU VỰC PREVIEW TÀI LIỆU: MẶC ĐỊNH ĐÓNG, CLICK VÀO MỚI MỞ RA -->
                                <div class="collapse border-top" id="previewDocCollapse{{ $doc->id }}">
                                    <div class="p-3 bg-white">
                                        @if(($doc->isPdf() || $doc->hasConvertedPdf()) && $doc->file_path)
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                            <span class="small fw-semibold text-muted">
                                                Trình xem trực tuyến {{ $doc->hasConvertedPdf() ? '(Word/PowerPoint đã chuyển đổi PDF)' : 'PDF' }}
                                            </span>
                                            <a href="{{ route('courses.documents.show', [$course, $doc]) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2 small">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>Mở toàn màn hình
                                            </a>
                                        </div>
                                        <iframe src="{{ route('courses.documents.show', [$course, $doc]) }}#toolbar=1" class="w-100 rounded border" style="height: 500px;" title="{{ $doc->title }}"></iframe>
                                        @elseif($doc->isOffice() && $doc->file_path)
                                        <div class="text-center p-4 bg-light rounded border">
                                            <i class="bi {{ $doc->typeIcon() }} display-4 mb-2 text-secondary"></i>
                                            <h4 class="h6 fw-bold mb-1">{{ $doc->original_name }}</h4>
                                            <p class="small text-muted mb-3">Tệp tài liệu văn phòng ({{ $doc->formattedSize() }}).</p>
                                            <div class="d-flex justify-content-center gap-2">
                                                <a href="{{ route('courses.documents.show', [$course, $doc]) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye me-1"></i>Xem tệp
                                                </a>
                                                @if($doc->allow_download)
                                                <a href="{{ route('courses.documents.download', [$course, $doc]) }}" class="btn btn-sm btn-success">
                                                    <i class="bi bi-download me-1"></i>Tải về máy
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
                                                <i class="bi bi-box-arrow-up-right me-1"></i>Truy cập liên kết
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
                             @include('documents._edit-modal', ['document' => $doc])
                            @endforeach
                        </div>
                        @else
                        <div class="p-4 text-center border rounded-3 bg-light text-muted">
                            <i class="bi bi-file-earmark-arrow-up fs-1 d-block mb-2 text-secondary"></i>
                            <h4 class="h6 fw-semibold text-dark mb-1">Chưa có tệp đính kèm nào</h4>
                            <p class="small text-muted mb-3">Bấm nút "Tải lên tài liệu" để thêm tài liệu PDF, tài liệu Word, bài tập hoặc tài liệu tham khảo cho bài học.</p>
                            <button type="button" class="btn btn-outline-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalUploadDocument">
                                <i class="bi bi-upload me-1"></i>Tải lên tài liệu ngay
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @else
            <x-empty-state
                icon="book"
                title="Chưa có bài giảng nào"
                message="Vui lòng bấm '+ Thêm chương mới' và '+ Bài giảng' ở cột bên trái để bắt đầu thiết kế nội dung bài học." />
            @endif
        </div>
    </div>
    @elseif($activeTab === 'info')
    <!-- ============================================================ -->
    <!-- TAB THÔNG TIN KHÓA HỌC                                        -->
    <!-- ============================================================ -->
    <div class="p-4">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="border rounded-3 p-4 h-100 bg-white shadow-2xs">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="badge bg-primary">{{ $course->subject?->name }}</span>
                        <span class="badge {{ $course->status === 'public' ? 'bg-success' : ($course->status === 'draft' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                            {{ $course->status === 'public' ? 'Công khai' : ($course->status === 'draft' ? 'Bản nháp' : 'Lưu trữ') }}
                        </span>
                    </div>
                    <div class="row g-3 align-items-center mb-4">
                        <div class="col-md-5">
                            <div class="border rounded-3 overflow-hidden shadow-sm" style="max-height: 180px;">
                                <img src="{{ $course->cover_url }}" alt="{{ $course->title }}" class="w-100 h-100 object-fit-cover">
                            </div>
                        </div>
                        <div class="col-md-7">
                            <h1 class="h4 mb-2 fw-bold text-dark">{{ $course->title }}</h1>
                            <p class="text-muted small mb-0">{{ $course->description ?: 'Khóa học chưa có phần mô tả.' }}</p>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('courses.edit', $course) }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil me-1"></i>Sửa thông tin & ảnh bìa
                        </a>
                        <a href="{{ route('courses.show', ['course' => $course, 'tab' => 'content']) }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-book-half me-1"></i>Vào soạn nội dung học
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="border rounded-3 p-4 h-100 bg-white shadow-2xs">
                    <h2 class="h6 text-uppercase fw-bold text-muted mb-3">Thông số khóa học</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Giảng viên</dt>
                        <dd class="col-7 fw-semibold">{{ $course->teacher?->name ?: 'Chưa cập nhật' }}</dd>
                        <dt class="col-5 text-muted">Thời gian</dt>
                        <dd class="col-7">
                            {{ $course->start_at?->format('d/m/Y') ?: 'Bắt đầu ngay' }} - {{ $course->end_at?->format('d/m/Y') ?: 'Vô thời hạn' }}
                        </dd>
                        <dt class="col-5 text-muted">Số chương</dt>
                        <dd class="col-7">{{ $course->chapters->count() }} chương</dd>
                        <dt class="col-5 text-muted">Số bài học</dt>
                        <dd class="col-7">{{ $allLessons->count() }} bài học</dd>
                        <dt class="col-5 text-muted">Tổng học liệu</dt>
                        <dd class="col-7">{{ $course->documents()->count() }} tài liệu</dd>
                        <dt class="col-5 text-muted">Học viên</dt>
                        <dd class="col-7">{{ $course->enrollments->count() }} học viên</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
    @elseif($activeTab === 'students')
    <!-- ============================================================ -->
    <!-- TAB HỌC VIÊN CỦA KHÓA HỌC                                    -->
    <!-- ============================================================ -->
    <div class="p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 mb-1 fw-bold">Danh sách học viên tham gia</h2>
                <p class="small text-muted mb-0">Học viên được cấp quyền truy cập bài học và tài liệu của khóa học này.</p>
            </div>
            <a href="{{ route('courses.students.index', $course) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-person-plus me-1"></i>Quản lý & Thêm học viên
            </a>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Học viên</th>
                        <th>Email</th>
                        <th>Trạng thái</th>
                        <th>Ngày tham gia</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($course->enrollments as $enrollment)
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">{{ $enrollment->user?->name }}</div>
                            <div class="small text-muted">{{ $enrollment->user?->username }}</div>
                        </td>
                        <td>{{ $enrollment->user?->email }}</td>
                        <td>
                            <span class="badge {{ $enrollment->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                {{ $enrollment->status === 'active' ? 'Đang học' : 'Đã khóa' }}
                            </span>
                        </td>
                        <td>{{ $enrollment->created_at?->format('d/m/Y') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4">
                            <x-empty-state title="Chưa có học viên" message="Bấm 'Quản lý & Thêm học viên' để ghi danh học viên vào khóa học." />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @elseif($activeTab === 'progress')
    <!-- ============================================================ -->
    <!-- TAB TIẾN ĐỘ HỌC TẬP CỦA HỌC VIÊN                             -->
    <!-- ============================================================ -->
    <div class="p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1 fw-bold text-dark">
                    <i class="bi bi-bar-chart-line text-primary me-2"></i>Tiến độ học tập & Theo dõi xem video của học viên
                </h2>
                <p class="small text-muted mb-0">Hệ thống đồng bộ tiến độ thời gian thực giữa giảng viên và học viên: ghi nhận chính xác từng giây video đã xem và bài học hoàn thành.</p>
            </div>
            <span class="badge bg-primary fs-6">{{ $course->enrollments->count() }} học viên</span>
        </div>

        <!-- BẢNG THỐNG KÊ TIẾN ĐỘ ĐỒNG BỘ CHI TIẾT -->
        <div class="table-responsive border rounded-3 overflow-hidden shadow-2xs mb-4">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 220px;">Học viên</th>
                        <th>Trạng thái</th>
                        <th style="min-width: 240px;">Tiến độ khóa học</th>
                        <th class="text-center">Số bài đã học</th>
                        <th class="text-end">Chi tiết tiến độ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($course->enrollments as $enrollment)
                    @php
                    $stUser = $enrollment->user;
                    $stPercent = $stUser ? $course->progressPercentageFor($stUser) : 0;
                    $stProgresses = $stUser ? $course->progresses->where('user_id', $stUser->id) : collect();
                    $stCompleted = $stProgresses->where('is_completed', true)->count();
                    $totalL = $allLessons->count();
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">{{ $stUser?->name }}</div>
                            <div class="small text-muted">{{ $stUser?->email }}</div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $stUser ? $course->progressStatusTone($stUser) : 'secondary' }}">
                                {{ $stUser ? $course->progressStatusLabel($stUser) : 'Chưa học' }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                <span class="text-muted fw-semibold">Tổng quan</span>
                                <span class="fw-bold text-primary">{{ $stPercent }}%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $stPercent }}%"></div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border fw-semibold">
                                {{ $stCompleted }} / {{ $totalL }} bài
                            </span>
                        </td>
                        <td class="text-end">
                            @if($stUser)
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#studentDetail{{ $stUser->id }}" aria-expanded="false">
                                <i class="bi bi-clock-history me-1"></i>Xem video & bài học
                            </button>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>

                    <!-- VÙNG MỞ RỘNG CHI TIẾT TỪNG BÀI HỌC VÀ TIẾN ĐỘ XEM VIDEO CỦA HỌC VIÊN -->
                    @if($stUser)
                    <tr class="collapse bg-light-subtle" id="studentDetail{{ $stUser->id }}">
                        <td colspan="5" class="p-3">
                            <div class="card border rounded-3 p-3 bg-white shadow-2xs">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                    <div class="fw-bold small text-uppercase text-primary">
                                        <i class="bi bi-play-circle-fill text-danger me-1"></i>Nhật ký xem video & hoàn thành bài giảng: {{ $stUser->name }}
                                    </div>
                                    <span class="badge bg-light text-muted border small">Đồng bộ tự động</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0">
                                        <thead class="table-light small">
                                            <tr>
                                                <th>Tên bài học</th>
                                                <th>Hình thức</th>
                                                <th>Thời lượng đã xem</th>
                                                <th style="min-width: 180px;">Tiến độ xem video</th>
                                                <th>Trạng thái hoàn thành</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small">
                                            @foreach($course->chapters as $ch)
                                            <tr class="table-light fw-bold text-secondary">
                                                <td colspan="5">
                                                    <i class="bi bi-folder me-1 text-warning"></i>{{ $ch->title }}
                                                </td>
                                            </tr>
                                            @foreach($ch->lessons as $les)
                                            @php
                                            $lesProg = $stProgresses->firstWhere('lesson_id', $les->id);
                                            $lesVideo = $les->documents->first(fn ($d) => $d->isVideo());
                                            $isLesCompleted = $lesProg?->is_completed ?? false;
                                            $watchedPct = $lesProg ? $lesProg->watchedPercentage() : 0;
                                            @endphp
                                            <tr>
                                                <td class="ps-3">
                                                    <i class="bi {{ $lesVideo ? 'bi-play-btn-fill text-danger' : 'bi-file-earmark-text text-secondary' }} me-1"></i>
                                                    {{ $les->title }}
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-muted border">
                                                        {{ $lesVideo ? 'Video bài giảng' : 'Tài liệu tệp' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($lesVideo)
                                                    @if($lesProg && ($lesProg->last_position_seconds > 0 || $lesProg->duration_seconds > 0))
                                                    <span class="font-monospace text-dark fw-semibold">
                                                        @if($lesProg->formattedDuration() !== '—')
                                                        @if($lesProg->formattedDuration() !== '—')
                                                            {{ $lesProg->formattedPosition() }} / {{ $lesProg->formattedDuration() }}
                                                        @else
                                                            {{ $lesProg->formattedPosition() }} <span class="text-muted small fst-italic">(Đang cập nhật tổng thời lượng)</span>
                                                        @endif
                                                        @else
                                                        {{ $lesProg->formattedPosition() }} <span class="text-muted small fst-italic">(Đang cập nhật tổng thời lượng)</span>
                                                        @endif
                                                    </span>
                                                    @else
                                                    <span class="text-muted">Chưa xem (00:00)</span>
                                                    @endif
                                                    @else
                                                    <span class="text-muted">Đọc tài liệu</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($lesVideo)
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress flex-grow-1" style="height: 6px;">
                                                            <div class="progress-bar {{ $isLesCompleted ? 'bg-success' : 'bg-primary' }}" style="width: {{ $watchedPct }}%"></div>
                                                        </div>
                                                        <span class="fw-bold small {{ $isLesCompleted ? 'text-success' : 'text-primary' }}">{{ $watchedPct }}%</span>
                                                    </div>
                                                    @else
                                                    <span class="text-muted">{{ $isLesCompleted ? '100% (Đã học)' : '0%' }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($isLesCompleted)
                                                    <span class="badge bg-success-subtle text-success border border-success">
                                                        <i class="bi bi-check-circle-fill me-1"></i>Đã hoàn thành
                                                        @if($lesProg?->completed_at)
                                                        ({{ $lesProg->completed_at->format('d/m/Y H:i') }})
                                                        @endif
                                                    </span>
                                                    @elseif($watchedPct > 0)
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning">
                                                        <i class="bi bi-clock-history me-1"></i>Đang xem ({{ $watchedPct }}%)
                                                    </span>
                                                    @else
                                                    <span class="badge bg-secondary-subtle text-secondary border">
                                                        Chưa học
                                                    </span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state title="Chưa có dữ liệu tiến độ" message="Khi có học viên tham gia và bắt đầu học, tiến độ xem video sẽ được thống kê tại đây." />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @elseif($activeTab === 'documents')
    <!-- ============================================================ -->
    <!-- TAB TÀI LIỆU CỦA KHÓA HỌC                                    -->
    <!-- ============================================================ -->
    <div class="p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="h5 mb-1 fw-bold text-dark">
                    <i class="bi bi-folder2-open text-primary me-2"></i>Tài liệu & Học liệu của khóa học
                </h2>
                <p class="small text-muted mb-0">Quản lý toàn bộ tài liệu đính kèm, video bài giảng và học liệu được tải lên trong khóa học này.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('courses.documents.index', $course) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-cloud-arrow-up me-1"></i>Quản lý &amp; Tải lên học liệu mới
                </a>
            </div>
        </div>

        @php
            $courseDocs = $course->documents()->with('lesson.chapter')->latest()->get();
        @endphp

        <div class="table-responsive border rounded-3 overflow-hidden shadow-2xs mb-4">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Học liệu</th>
                        <th>Gắn với bài học</th>
                        <th>Loại</th>
                        <th>Trạng thái duyệt</th>
                        <th>Quyền học viên</th>
                        <th class="text-center">Lượt xem/tải</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($courseDocs as $cDoc)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi {{ $cDoc->typeIcon() }} fs-4"></i>
                                <div>
                                    <div class="fw-semibold text-dark">{{ $cDoc->title }}</div>
                                    <div class="small text-muted">
                                        @if($cDoc->file_path)
                                            {{ $cDoc->original_name }} · {{ $cDoc->formattedSize() }}
                                            @if($cDoc->hasConvertedPdf())
                                                <span class="badge bg-success-subtle text-success border border-success-subtle ms-1">
                                                    <i class="bi bi-file-earmark-pdf"></i> PDF Preview
                                                </span>
                                            @endif
                                        @elseif($cDoc->url)
                                            <span class="text-truncate d-inline-block" style="max-width: 220px;">{{ $cDoc->url }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($cDoc->lesson)
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-journal-bookmark me-1"></i>{{ $cDoc->lesson->title }}
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    Tài liệu chung
                                </span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                {{ $cDoc->typeLabel() }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $cDoc->statusBadgeClass() }}">
                                {{ $cDoc->statusLabel() }}
                            </span>
                            @if($cDoc->isRejected() && $cDoc->rejection_reason)
                                <div class="small text-danger mt-1 fst-italic">
                                    <i class="bi bi-info-circle me-1"></i>{{ $cDoc->rejection_reason }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="small d-flex flex-column gap-1">
                                <div><i class="bi {{ $cDoc->allow_view ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }} me-1"></i>Xem trực tuyến</div>
                                <div><i class="bi {{ $cDoc->allow_download ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }} me-1"></i>Tải về</div>
                            </div>
                        </td>
                        <td class="text-center small text-muted">
                            <div><i class="bi bi-eye text-primary me-1"></i>{{ $cDoc->logs()->where('action', 'view')->count() }}</div>
                            <div><i class="bi bi-download text-success me-1"></i>{{ $cDoc->logs()->where('action', 'download')->count() }}</div>
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="d-inline-flex align-items-center gap-1">
                                @if(auth()->user()->hasRole('admin'))
                                    <!-- NHÓM KIỂM DUYỆT (ADMIN) -->
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Kiểm duyệt">
                                        <form method="POST" action="{{ route('admin.documents.approve', $cDoc) }}" class="d-inline" onsubmit="return confirm('Xác nhận phê duyệt tài liệu: {{ addslashes($cDoc->title) }}?');">
                                            @csrf
                                            <button type="submit" 
                                                    class="btn btn-sm {{ $cDoc->isApproved() ? 'btn-success text-white' : 'btn-outline-success' }}" 
                                                    title="{{ $cDoc->isApproved() ? 'Tài liệu đã được duyệt (Bấm để phê duyệt lại)' : 'Bấm để phê duyệt tài liệu này' }}">
                                                <i class="bi {{ $cDoc->isApproved() ? 'bi-check-circle-fill' : 'bi-check-circle' }} me-1"></i>{{ $cDoc->isApproved() ? 'Đã duyệt' : 'Duyệt' }}
                                            </button>
                                        </form>

                                        <button type="button" 
                                                class="btn btn-sm {{ $cDoc->isRejected() ? 'btn-danger text-white' : 'btn-outline-warning' }}" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#tabRejectModal{{ $cDoc->id }}" 
                                                title="{{ $cDoc->isRejected() ? 'Tài liệu đã bị từ chối (Bấm để xem hoặc sửa lý do)' : 'Bấm để từ chối tài liệu này' }}">
                                            <i class="bi {{ $cDoc->isRejected() ? 'bi-x-circle-fill' : 'bi-x-circle' }} me-1"></i>{{ $cDoc->isRejected() ? 'Đã từ chối' : 'Từ chối' }}
                                        </button>
                                    </div>
                                @endif

                                <!-- NHÓM THAO TÁC TÀI LIỆU -->
                                <div class="btn-group btn-group-sm" role="group" aria-label="Thao tác">
                                    @if($cDoc->allow_view || auth()->user()->can('update', $course))
                                        <a href="{{ route('courses.documents.show', [$course, $cDoc]) }}" target="_blank" class="btn btn-outline-primary" title="Xem trực tuyến">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endif
                                    @if($cDoc->allow_download || auth()->user()->can('update', $course))
                                        <a href="{{ route('courses.documents.download', [$course, $cDoc]) }}" class="btn btn-outline-success" title="Tải về máy">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    @endif
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editDocumentModal{{ $cDoc->id }}" title="Chỉnh sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('courses.documents.destroy', [$course, $cDoc]) }}" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài liệu này?')" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Xóa">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            @include('documents._edit-modal', ['document' => $cDoc])

                            @if(auth()->user()->hasRole('admin'))
                            <!-- MODAL TỪ CHỐI -->
                            <div class="modal fade" id="tabRejectModal{{ $cDoc->id }}" tabindex="-1" aria-hidden="true" style="text-align: left;">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('admin.documents.reject', $cDoc) }}">
                                            @csrf
                                            <div class="modal-header bg-light">
                                                <h5 class="modal-title h6 fw-bold text-danger">Từ chối tài liệu: {{ $cDoc->title }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="tab_reason_{{ $cDoc->id }}" class="form-label small fw-semibold">Lý do từ chối:</label>
                                                    <textarea name="reason" id="tab_reason_{{ $cDoc->id }}" class="form-control" rows="3" required placeholder="Nhập lý do chưa đạt yêu cầu...">{{ $cDoc->rejection_reason }}</textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                                                <button type="submit" class="btn btn-sm btn-danger">Xác nhận từ chối</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">
                            <x-empty-state title="Chưa có tài liệu trong khóa học" message="Bấm 'Quản lý & Tải lên học liệu mới' để bổ sung tài liệu cho khóa học này." />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

<!-- ============================================================ -->
<!-- MODAL: TẢI LÊN TÀI LIỆU ĐÍNH KÈM CHO BÀI HỌC                 -->
<!-- ============================================================ -->
@if($currentLesson)
<div class="modal fade" id="modalUploadDocument" tabindex="-1" aria-labelledby="modalUploadDocumentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalUploadDocumentLabel">
                    <i class="bi bi-file-earmark-plus text-primary me-2"></i>Tải lên tệp đính kèm
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formUploadDocument" method="POST" action="{{ route('courses.documents.store', $course) }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type" value="file">
                <input type="hidden" name="lesson_id" value="{{ $currentLesson->id }}">
                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Bài giảng đính kèm</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="{{ $currentLesson->title }}" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="doc_file" class="form-label small fw-semibold">Chọn tệp tài liệu từ máy tính <span class="text-danger">*</span></label>
                        <input type="file" name="file" id="doc_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt" required>
                        <div class="form-text small">Hỗ trợ PDF, Word, Excel, PowerPoint (tối đa 20 MB). Hệ thống tự động lấy tên tệp làm tên tài liệu.</div>
                    </div>

                    <div class="p-3 bg-light rounded border mb-2">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="allow_view" id="doc_allow_view" value="1" checked>
                            <label class="form-check-label small" for="doc_allow_view">
                                Cho phép học viên xem trực tuyến (PDF, tài liệu)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allow_download" id="doc_allow_download" value="1" checked>
                            <label class="form-check-label small" for="doc_allow_download">
                                Cho phép học viên tải tệp về máy tính
                            </label>
                        </div>
                    </div>

                    <!-- THANH TIẾN TRÌNH TẢI LÊN TÀI LIỆU -->
                    <div id="doc_upload_progress" class="d-none mt-3 p-3 bg-white rounded border border-primary">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold text-primary" id="doc_progress_status">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span> Đang tải tệp lên...
                            </span>
                            <span class="small fw-bold text-primary" id="doc_progress_percent">0%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="doc_progress_bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="small text-muted mt-2 d-flex justify-content-between">
                            <span id="doc_progress_bytes">0 MB / 0 MB</span>
                            <span class="fst-italic text-secondary">Vui lòng không đóng trình duyệt</span>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="doc_btn_cancel">Hủy</button>
                    <button type="submit" class="btn btn-primary" id="doc_btn_submit"><i class="bi bi-upload me-1"></i>Tải lên ngay</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: THÊM VIDEO BÀI GIẢNG                                  -->
<!-- ============================================================ -->
<div class="modal fade" id="modalAddVideo" tabindex="-1" aria-labelledby="modalAddVideoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalAddVideoLabel">
                    <i class="bi bi-camera-video text-danger me-2"></i>Thêm video bài giảng
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formUploadVideo" method="POST" action="{{ route('courses.documents.store', $course) }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type" value="video">
                <input type="hidden" name="lesson_id" value="{{ $currentLesson->id }}">
                <input type="hidden" name="allow_view" value="1">
                <input type="hidden" name="allow_download" value="0">
                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Bài giảng áp dụng</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="{{ $currentLesson->title }}" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="video_title" class="form-label small fw-semibold">Tiêu đề video <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="video_title" class="form-control" value="Video: {{ $currentLesson->title }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Hình thức video</label>
                        <ul class="nav nav-pills nav-fill mb-3" id="videoSourceTab" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active btn-sm" id="pills-link-tab" data-bs-toggle="pill" data-bs-target="#pills-link" type="button">Link YouTube / Drive</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link btn-sm" id="pills-file-tab" data-bs-toggle="pill" data-bs-target="#pills-file" type="button">Tải tệp MP4</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="videoSourceTabContent">
                            <div class="tab-pane fade show active" id="pills-link">
                                <label for="video_url" class="form-label small text-muted">Dán đường dẫn (URL) YouTube hoặc Google Drive</label>
                                <input type="url" name="url" id="video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=... hoặc link Drive">
                                <div class="form-text small mt-1">Khuyên dùng cho video bài giảng dài: xem mượt mà, không giới hạn dung lượng.</div>
                            </div>
                            <div class="tab-pane fade" id="pills-file">
                                <label for="video_file" class="form-label small text-muted">Chọn tệp MP4 từ máy tính</label>
                                <input type="file" name="file" id="video_file" class="form-control" accept="video/mp4,video/webm,video/ogg">
                                <div class="form-text small">Hỗ trợ MP4, WebM (tối đa 500 MB). Có thanh theo dõi tiến độ tải lên.</div>
                            </div>
                        </div>
                    </div>

                    <!-- THANH TIẾN TRÌNH TẢI LÊN VIDEO -->
                    <div id="video_upload_progress" class="d-none mt-3 p-3 bg-white rounded border border-danger">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold text-danger" id="video_progress_status">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span> Đang tải video lên...
                            </span>
                            <span class="small fw-bold text-danger" id="video_progress_percent">0%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" id="video_progress_bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="small text-muted mt-2 d-flex justify-content-between">
                            <span id="video_progress_bytes">0 MB / 0 MB</span>
                            <span class="fst-italic text-secondary">Tệp lớn có thể mất vài phút</span>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="video_btn_cancel">Hủy</button>
                    <button type="submit" class="btn btn-danger" id="video_btn_submit"><i class="bi bi-save me-1"></i>Lưu video</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SCRIPT XỬ LÝ THANH TIẾN TRÌNH TẢI LÊN AJAX -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        function setupUploadProgress(formId, config) {
            const form = document.getElementById(formId);
            if (!form) return;

            form.addEventListener('submit', function(e) {
                const fileInput = form.querySelector('input[type="file"]');
                // If no file is selected (e.g. video link tab), let normal submit happen
                if (!fileInput || !fileInput.files || !fileInput.files.length) {
                    return;
                }

                e.preventDefault();

                const file = fileInput.files[0];
                const formData = new FormData(form);
                const xhr = new XMLHttpRequest();

                const container = document.getElementById(config.containerId);
                const bar = document.getElementById(config.barId);
                const percent = document.getElementById(config.percentId);
                const status = document.getElementById(config.statusId);
                const bytes = document.getElementById(config.bytesId);
                const submitBtn = document.getElementById(config.submitBtnId);
                const cancelBtn = document.getElementById(config.cancelBtnId);

                if (container) container.classList.remove('d-none');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang xử lý...';
                }
                if (cancelBtn) cancelBtn.disabled = true;

                xhr.upload.addEventListener('progress', function(event) {
                    if (event.lengthComputable) {
                        const percentComplete = Math.round((event.loaded / event.total) * 100);
                        if (bar) bar.style.width = percentComplete + '%';
                        if (percent) percent.innerText = percentComplete + '%';

                        const loadedMb = (event.loaded / (1024 * 1024)).toFixed(1);
                        const totalMb = (event.total / (1024 * 1024)).toFixed(1);
                        if (bytes) bytes.innerText = `${loadedMb} MB / ${totalMb} MB`;

                        if (percentComplete >= 100) {
                            if (bar) {
                                bar.classList.remove('progress-bar-striped', 'progress-bar-animated');
                                bar.classList.add('bg-success');
                            }
                            if (status) {
                                status.innerHTML = '<span class="spinner-border spinner-border-sm me-1 text-success"></span> Đang xử lý và lưu tệp trên máy chủ, vui lòng chờ...';
                            }
                        }
                    }
                });

                xhr.addEventListener('load', function() {
                    if (xhr.status >= 200 && xhr.status < 400) {
                        if (bar) bar.style.width = '100%';
                        if (status) {
                            status.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i> Tải lên thành công! Đang tải lại trang...';
                        }
                        setTimeout(function() {
                            window.location.reload();
                        }, 600);
                    } else {
                        if (container) container.classList.add('d-none');
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerText = 'Thử lại';
                        }
                        if (cancelBtn) cancelBtn.disabled = false;
                        let msg = 'Tải lên thất bại. Vui lòng kiểm tra dung lượng tệp hoặc thử lại.';
                        try {
                            const res = JSON.parse(xhr.responseText);
                            if (res.errors) {
                                msg = Object.values(res.errors).flat().join('\n');
                            } else if (res.message) {
                                msg = res.message;
                            }
                        } catch (e) {}
                        alert(msg);
                    }
                });

                xhr.addEventListener('error', function() {
                    if (container) container.classList.add('d-none');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerText = 'Thử lại';
                    }
                    if (cancelBtn) cancelBtn.disabled = false;
                    alert('Có lỗi mạng xảy ra khi tải tệp. Vui lòng thử lại.');
                });

                xhr.open('POST', form.action);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.send(formData);
            });
        }

        setupUploadProgress('formUploadDocument', {
            containerId: 'doc_upload_progress',
            barId: 'doc_progress_bar',
            percentId: 'doc_progress_percent',
            statusId: 'doc_progress_status',
            bytesId: 'doc_progress_bytes',
            submitBtnId: 'doc_btn_submit',
            cancelBtnId: 'doc_btn_cancel'
        });

        setupUploadProgress('formUploadVideo', {
            containerId: 'video_upload_progress',
            barId: 'video_progress_bar',
            percentId: 'video_progress_percent',
            statusId: 'video_progress_status',
            bytesId: 'video_progress_bytes',
            submitBtnId: 'video_btn_submit',
            cancelBtnId: 'video_btn_cancel'
        });

        // Xử lý hiện nút "Cập nhật" khi có thay đổi tên bài giảng
        const titleInput = document.getElementById('lesson_title_input');
        const updateBtn = document.getElementById('lesson_update_btn');
        const resubmitBtn = document.getElementById('lesson_resubmit_btn');
        if (titleInput) {
            const originalTitle = titleInput.dataset.original || '';
            titleInput.addEventListener('input', function() {
                const hasChanges = this.value.trim() !== originalTitle.trim() && this.value.trim().length > 0;
                if (updateBtn) updateBtn.classList.toggle('d-none', !hasChanges);
                if (resubmitBtn) resubmitBtn.classList.toggle('d-none', !hasChanges);
            });
        }
    });

    window.cancelLessonEdit = function() {
        const titleInput = document.getElementById('lesson_title_input');
        const updateBtn = document.getElementById('lesson_update_btn');
        const resubmitBtn = document.getElementById('lesson_resubmit_btn');
        if (!titleInput) return;
        titleInput.value = titleInput.dataset.original || '';
        if (updateBtn) updateBtn.classList.add('d-none');
        if (resubmitBtn) resubmitBtn.classList.add('d-none');
    };

    window.setLessonStatus = function(newStatus) {
        const statusInput = document.getElementById('lesson_status_input');
        const form = document.getElementById('lesson_edit_form');
        if (statusInput && form) {
            statusInput.value = newStatus;
            form.submit();
        }
    };
</script>
@endif
@endsection
