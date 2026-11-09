@extends('layouts.teacher')

@section('content')
<x-page-header eyebrow="GIẢNG VIÊN / HỌC LIỆU" title="Quản lý tài liệu bài giảng" description="Tải lên tài liệu, bài giảng (PDF, Word, PPT, video, liên kết). Tài liệu tải lên sẽ chờ Quản trị viên phê duyệt trước khi công khai cho học viên.">
    <x-slot:actions>
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#uploadSection" aria-expanded="true">
            <i class="bi bi-cloud-arrow-up me-1"></i>Tải lên tài liệu mới
        </button>
        <x-button :href="route('teacher.dashboard')" variant="outline-secondary">
            <i class="bi bi-journal-text me-1"></i>Khóa học của tôi
        </x-button>
    </x-slot:actions>
</x-page-header>

@if (session('status'))
    <x-alert tone="success" class="mb-4">{{ session('status') }}</x-alert>
@endif

<!-- KHU VỰC FORM TẢI LÊN TÀI LIỆU CỦA GIÁO VIÊN -->
<div class="collapse show mb-4" id="uploadSection">
    <x-card class="p-4 border-primary shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 mb-1 text-primary"><i class="bi bi-cloud-arrow-up me-2"></i>Thêm học liệu mới cho khóa học</h2>
                <p class="text-muted small mb-0">Hỗ trợ tệp tài liệu Word, PPT, PDF, video bài giảng hoặc link ngoài. Tự động chuyển Word/PowerPoint sang PDF để xem trực tuyến.</p>
            </div>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2">
                <i class="bi bi-shield-check me-1"></i>Kiểm duyệt bởi Admin
            </span>
        </div>

        <form id="teacherUploadDocumentForm" method="POST" action="{{ route('teacher.materials.store') }}" enctype="multipart/form-data">
            @csrf

            <!-- 1. CHỌN KHÓA HỌC & BÀI HỌC -->
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label for="course_id" class="form-label fw-semibold">Khóa học của bạn <span class="text-danger">*</span></label>
                    <select name="course_id" id="course_id" class="form-select @error('course_id') is-invalid @enderror" required onchange="handleCourseChange(this.value)">
                        <option value="">-- Chọn khóa học phụ trách --</option>
                        @foreach($courses as $c)
                            <option value="{{ $c->id }}" @selected(old('course_id') == $c->id)>
                                {{ $c->title }}
                            </option>
                        @endforeach
                    </select>
                    @error('course_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label for="lesson_id" class="form-label fw-semibold">Gắn vào bài học (tùy chọn)</label>
                    <select name="lesson_id" id="lesson_id" class="form-select @error('lesson_id') is-invalid @enderror">
                        <option value="">-- Tài liệu chung của toàn khóa học --</option>
                        @foreach($courses as $c)
                            @foreach($c->chapters as $ch)
                                <optgroup label="{{ $c->title }} &gt; {{ $ch->title }}" data-course-id="{{ $c->id }}" style="{{ old('course_id') == $c->id ? '' : 'display: none;' }}">
                                    @foreach($ch->lessons as $les)
                                        <option value="{{ $les->id }}" @selected(old('lesson_id') == $les->id)>
                                            {{ $les->title }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        @endforeach
                    </select>
                    @error('lesson_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <!-- 2. CHỌN LOẠI HỌC LIỆU -->
            @php($selectedType = old('type', 'file'))
            <div class="mb-3">
                <label class="form-label fw-semibold">Loại học liệu <span class="text-danger">*</span></label>
                <div class="d-flex flex-wrap gap-3 p-3 bg-light rounded border">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="type" id="type_file" value="file" @checked($selectedType === 'file') onchange="toggleTypeFields('file')">
                        <label class="form-check-label fw-medium" for="type_file">
                            <i class="bi bi-file-earmark-text text-primary me-1"></i>Tệp tài liệu (PDF, Word, PPT, Excel)
                        </label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="type" id="type_video" value="video" @checked($selectedType === 'video') onchange="toggleTypeFields('video')">
                        <label class="form-check-label fw-medium" for="type_video">
                            <i class="bi bi-play-circle text-danger me-1"></i>Video bài giảng (MP4 hoặc liên kết)
                        </label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="type" id="type_link" value="link" @checked($selectedType === 'link') onchange="toggleTypeFields('link')">
                        <label class="form-check-label fw-medium" for="type_link">
                            <i class="bi bi-link-45deg text-info me-1"></i>Liên kết ngoài (URL)
                        </label>
                    </div>
                </div>
                @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <!-- 3. TIÊU ĐỀ -->
            <div class="mb-3">
                <label for="title" class="form-label fw-semibold">Tiêu đề học liệu</label>
                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" placeholder="Để trống nếu muốn tự động lấy theo tên tệp">
                @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <!-- 4. KÉO THẢ TỆP -->
            <div class="mb-3" id="file_container" style="{{ $selectedType === 'link' ? 'display: none;' : '' }}">
                <label class="form-label fw-semibold">
                    Tệp tải lên <span class="text-danger" id="file_required_mark">*</span>
                </label>

                <div id="drop_zone" class="border border-2 border-dashed rounded p-4 text-center bg-light position-relative" style="cursor: pointer; transition: background-color 0.2s, border-color 0.2s;">
                    <input type="file" name="file" id="file" class="position-absolute top-0 start-0 w-100 h-100 opacity-0 @error('file') is-invalid @enderror" style="cursor: pointer;" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.mp4,.webm" @if($selectedType === 'file') required @endif>
                    <div id="drop_zone_prompt">
                        <i class="bi bi-cloud-arrow-up display-5 text-primary"></i>
                        <div class="fw-semibold mt-2">Kéo thả tệp vào đây hoặc bấm để chọn tệp từ máy tính</div>
                        <div class="text-muted small mt-1" id="file_type_hint">
                            Hỗ trợ PDF, Word (.doc, .docx), PowerPoint (.ppt, .pptx), Excel (.xls, .xlsx). Tối đa 20 MB.<br>
                            <span class="text-success"><i class="bi bi-magic me-1"></i>Hệ thống tự động chuyển Word &amp; PowerPoint sang PDF để học viên xem trực tuyến.</span>
                        </div>
                    </div>
                    <div id="file_info_preview" class="d-none text-success fw-semibold mt-2 p-2 bg-white rounded border d-inline-flex align-items-center">
                        <i class="bi bi-check-circle-fill text-success me-2 fs-5"></i>
                        <span id="file_name_display" class="me-2 text-dark"></span>
                        <span class="badge bg-secondary" id="file_size_display"></span>
                    </div>
                </div>
                @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <!-- 5. URL LINK -->
            <div class="mb-3" id="url_container" style="{{ in_array($selectedType, ['link', 'video']) ? '' : 'display: none;' }}">
                <label for="url" class="form-label fw-semibold">
                    Địa chỉ liên kết (URL) <span class="text-danger" id="url_required_mark" style="{{ $selectedType === 'link' ? '' : 'display: none;' }}">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-globe"></i></span>
                    <input type="url" name="url" id="url" class="form-control @error('url') is-invalid @enderror" placeholder="https://www.youtube.com/watch?v=... hoặc https://drive.google.com/..." value="{{ old('url') }}">
                </div>
                @error('url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <!-- 6. PHÂN QUYỀN HỌC VIÊN -->
            <div class="card bg-light p-3 mb-3 border">
                <label class="form-label fw-semibold mb-2">Cấu hình quyền học viên</label>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="allow_view" id="allow_view" value="1" @checked(old('allow_view', true))>
                    <label class="form-check-label fw-medium" for="allow_view">
                        <i class="bi bi-eye text-success me-1"></i>Cho phép xem trực tuyến
                    </label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="allow_download" id="allow_download" value="1" @checked(old('allow_download', true))>
                    <label class="form-check-label fw-medium" for="allow_download">
                        <i class="bi bi-download text-primary me-1"></i>Cho phép tải về máy tính
                    </label>
                </div>
            </div>

            <!-- 7. THANH TIẾN TRÌNH TẢI LÊN THỰC TẾ -->
            <div id="upload_progress_wrapper" class="d-none mb-3 p-3 bg-white rounded border border-primary">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold text-primary" id="upload_progress_status">
                        <span class="spinner-border spinner-border-sm me-1" role="status"></span> Đang tải tệp lên máy chủ...
                    </span>
                    <span class="small fw-bold text-primary" id="upload_progress_percent">0%</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div id="upload_progress_bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%"></div>
                </div>
                <div class="small text-muted mt-2 d-flex justify-content-between">
                    <span id="upload_progress_bytes">0 MB / 0 MB</span>
                    <span class="fst-italic text-secondary">Tự động chuyển đổi PDF khi hoàn tất</span>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#uploadSection">Hủy</button>
                <button type="submit" class="btn btn-primary" id="btn_submit_upload">
                    <i class="bi bi-cloud-arrow-up me-1"></i>Tải lên (Gửi phê duyệt)
                </button>
            </div>
        </form>
    </x-card>
</div>

<!-- DANH SÁCH HỌC LIỆU CỦA GIẢNG VIÊN -->
<x-card class="p-4 shadow-sm">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="h5 mb-1">Học liệu thuộc các khóa học của bạn ({{ $documents->total() }})</h2>
            <p class="text-muted small mb-0">Theo dõi trạng thái kiểm duyệt của Quản trị viên và lượt xem của học viên.</p>
        </div>

        <!-- BỘ LỌC -->
        <form method="GET" action="{{ route('teacher.materials.index') }}" class="d-flex flex-wrap gap-2 align-items-center">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width: 180px;" placeholder="Tìm tên tài liệu...">

            <select name="course_id" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="">-- Tất cả khóa học --</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id }}" @selected(request('course_id') == $c->id)>{{ $c->title }}</option>
                @endforeach
            </select>

            <select name="type" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="">-- Loại --</option>
                <option value="file" @selected(request('type') === 'file')>Tệp</option>
                <option value="video" @selected(request('type') === 'video')>Video</option>
                <option value="link" @selected(request('type') === 'link')>Link</option>
            </select>

            <select name="status" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="">-- Trạng thái duyệt --</option>
                <option value="pending" @selected(request('status') === 'pending')>⏳ Chờ duyệt</option>
                <option value="approved" @selected(request('status') === 'approved')>✓ Đã duyệt</option>
                <option value="rejected" @selected(request('status') === 'rejected')>✕ Bị từ chối</option>
            </select>

            <button type="submit" class="btn btn-sm btn-primary">Lọc</button>
            @if(request()->hasAny(['q', 'course_id', 'type', 'status']))
                <a href="{{ route('teacher.materials.index') }}" class="btn btn-sm btn-outline-secondary">Đặt lại</a>
            @endif
        </form>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0 table-hover">
            <thead class="table-light">
                <tr>
                    <th>Học liệu</th>
                    <th>Khóa học / Bài học</th>
                    <th>Loại</th>
                    <th>Trạng thái duyệt</th>
                    <th>Quyền</th>
                    <th class="d-none d-lg-table-cell text-center">Lượt xem/tải</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $doc)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi {{ $doc->typeIcon() }} fs-4"></i>
                                <div>
                                    <div class="fw-semibold text-dark">{{ $doc->title }}</div>
                                    <div class="small text-muted">
                                        @if($doc->file_path)
                                            {{ $doc->original_name }} · {{ $doc->formattedSize() }}
                                            @if($doc->hasConvertedPdf())
                                                <span class="badge bg-success-subtle text-success border border-success-subtle ms-1">
                                                    <i class="bi bi-file-earmark-pdf"></i> PDF
                                                </span>
                                            @endif
                                        @elseif($doc->url)
                                            <span class="text-truncate d-inline-block" style="max-width: 220px;">{{ $doc->url }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="small fw-medium text-dark">{{ $doc->course->title }}</div>
                            <div class="small text-muted">
                                @if($doc->lesson)
                                    <i class="bi bi-journal-bookmark me-1"></i>{{ $doc->lesson->title }}
                                @else
                                    <span class="fst-italic text-secondary">Tài liệu chung</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                {{ $doc->typeLabel() }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $doc->statusBadgeClass() }}">
                                {{ $doc->statusLabel() }}
                            </span>
                            @if($doc->isRejected() && $doc->rejection_reason)
                                <div class="small text-danger mt-1 fst-italic" title="Lý do từ chối">
                                    <i class="bi bi-info-circle me-1"></i>{{ $doc->rejection_reason }}
                                </div>
                            @elseif($doc->isPending())
                                <div class="small text-muted mt-1 fst-italic">
                                    Đang chờ Admin duyệt
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-1 small">
                                <div>
                                    <i class="bi {{ $doc->allow_view ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }} me-1"></i>
                                    <span>Xem</span>
                                </div>
                                <div>
                                    <i class="bi {{ $doc->allow_download ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }} me-1"></i>
                                    <span>Tải</span>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-lg-table-cell text-center small text-muted">
                            <div><i class="bi bi-eye text-primary me-1"></i>{{ $doc->views_count }}</div>
                            <div><i class="bi bi-download text-success me-1"></i>{{ $doc->downloads_count }}</div>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if($doc->allow_view)
                                    <a href="{{ route('courses.documents.show', [$doc->course, $doc]) }}" target="_blank" class="btn btn-outline-primary" title="Xem trước tài liệu">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                @endif
                                @if($doc->allow_download)
                                    <a href="{{ route('courses.documents.download', [$doc->course, $doc]) }}" class="btn btn-outline-success" title="Tải về máy">
                                        <i class="bi bi-download"></i>
                                    </a>
                                @endif
                                <a href="{{ route('courses.documents.edit', [$doc->course, $doc]) }}" class="btn btn-outline-secondary" title="Sửa">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('courses.documents.destroy', [$doc->course, $doc]) }}" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài liệu này?')" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="Xóa">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox display-6 d-block mb-2 text-secondary"></i>
                            Bạn chưa có tài liệu nào trong các khóa học. Hãy tải lên tài liệu mới ở trên.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($documents->hasPages())
        <div class="mt-4">
            {{ $documents->links() }}
        </div>
    @endif
</x-card>

<script>
function handleCourseChange(courseId) {
    const lessonSelect = document.getElementById('lesson_id');
    const optgroups = lessonSelect.querySelectorAll('optgroup');

    optgroups.forEach(group => {
        if (!courseId || group.getAttribute('data-course-id') === courseId) {
            group.style.display = '';
        } else {
            group.style.display = 'none';
        }
    });

    lessonSelect.value = '';
}

function toggleTypeFields(type) {
    const fileContainer = document.getElementById('file_container');
    const urlContainer = document.getElementById('url_container');
    const fileInput = document.getElementById('file');
    const fileReqMark = document.getElementById('file_required_mark');
    const urlReqMark = document.getElementById('url_required_mark');
    const hint = document.getElementById('file_type_hint');

    if (type === 'file') {
        fileContainer.style.display = 'block';
        urlContainer.style.display = 'none';
        if (fileReqMark) fileReqMark.style.display = 'inline';
        if (urlReqMark) urlReqMark.style.display = 'none';
        hint.innerHTML = 'Hỗ trợ PDF, Word (.doc, .docx), PowerPoint (.ppt, .pptx), Excel (.xls, .xlsx). Tối đa 20 MB.<br><span class="text-success"><i class="bi bi-magic me-1"></i>Hệ thống tự động chuyển Word & PowerPoint sang PDF để học viên xem trực tuyến.</span>';
        fileInput.required = true;
    } else if (type === 'video') {
        fileContainer.style.display = 'block';
        urlContainer.style.display = 'block';
        if (fileReqMark) fileReqMark.style.display = 'none';
        if (urlReqMark) urlReqMark.style.display = 'none';
        hint.innerHTML = 'Tải tệp video MP4/WebM tối đa 500 MB hoặc dán link YouTube/Drive bên dưới.';
        fileInput.required = false;
    } else if (type === 'link') {
        fileContainer.style.display = 'none';
        urlContainer.style.display = 'block';
        if (fileReqMark) fileReqMark.style.display = 'none';
        if (urlReqMark) urlReqMark.style.display = 'inline';
        fileInput.required = false;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const dropZone = document.getElementById('drop_zone');
    const fileInput = document.getElementById('file');
    const fileInfo = document.getElementById('file_info_preview');
    const fileNameDisplay = document.getElementById('file_name_display');
    const fileSizeDisplay = document.getElementById('file_size_display');
    const form = document.getElementById('teacherUploadDocumentForm');

    if (dropZone && fileInput) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.classList.add('bg-white', 'border-primary', 'shadow');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.classList.remove('bg-white', 'border-primary', 'shadow');
            }, false);
        });

        fileInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                fileNameDisplay.textContent = file.name;
                const sizeInKb = (file.size / 1024).toFixed(0);
                const sizeInMb = (file.size / (1024 * 1024)).toFixed(1);
                fileSizeDisplay.textContent = file.size > 1048576 ? sizeInMb + ' MB' : sizeInKb + ' KB';
                fileInfo.classList.remove('d-none');

                const titleInput = document.getElementById('title');
                if (titleInput && !titleInput.value) {
                    const nameWithoutExt = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                    titleInput.value = nameWithoutExt;
                }
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            const fileInput = document.getElementById('file');
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                return;
            }

            e.preventDefault();

            const formData = new FormData(form);
            const xhr = new XMLHttpRequest();
            const progressWrapper = document.getElementById('upload_progress_wrapper');
            const progressBar = document.getElementById('upload_progress_bar');
            const progressPercent = document.getElementById('upload_progress_percent');
            const progressStatus = document.getElementById('upload_progress_status');
            const progressBytes = document.getElementById('upload_progress_bytes');
            const submitBtn = document.getElementById('btn_submit_upload');

            progressWrapper.classList.remove('d-none');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang tải lên...';

            xhr.upload.addEventListener('progress', function (event) {
                if (event.lengthComputable) {
                    const percentComplete = Math.round((event.loaded / event.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    progressPercent.innerText = percentComplete + '%';

                    const loadedMb = (event.loaded / (1024 * 1024)).toFixed(1);
                    const totalMb = (event.total / (1024 * 1024)).toFixed(1);
                    progressBytes.innerText = `${loadedMb} MB / ${totalMb} MB`;

                    if (percentComplete >= 100) {
                        progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
                        progressBar.classList.add('bg-success');
                        progressStatus.innerHTML = '<span class="spinner-border spinner-border-sm me-1 text-success"></span> Đang xử lý chuyển đổi Word/PowerPoint sang PDF trên máy chủ...';
                    }
                }
            });

            xhr.addEventListener('load', function () {
                if (xhr.status >= 200 && xhr.status < 400) {
                    progressBar.style.width = '100%';
                    progressStatus.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i> Tải lên thành công! Tài liệu đang chờ Quản trị viên duyệt.';
                    setTimeout(function () {
                        window.location.reload();
                    }, 800);
                } else {
                    progressWrapper.classList.add('d-none');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i>Thử tải lại';

                    let msg = 'Tải lên thất bại. Vui lòng kiểm tra định dạng và dung lượng tệp.';
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.errors) {
                            msg = Object.values(res.errors).flat().join('\n');
                        } else if (res.message) {
                            msg = res.message;
                        }
                    } catch (err) {}
                    alert(msg);
                }
            });

            xhr.addEventListener('error', function () {
                progressWrapper.classList.add('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i>Thử tải lại';
                alert('Có lỗi mạng xảy ra khi tải tệp. Vui lòng kiểm tra kết nối.');
            });

            xhr.open('POST', form.action);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        });
    }
});
</script>
@endsection

