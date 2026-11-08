@php
    $document ??= null;
    $selectedType = old('type', $document?->type ?? 'file');
    $selectedLessonId = old('lesson_id', $document?->lesson_id ?? request()->query('lesson_id'));
@endphp

<div class="mb-3">
    <label class="form-label fw-semibold">Loại học liệu <span class="text-danger">*</span></label>
    <div class="d-flex flex-wrap gap-3">
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="type" id="type_file" value="file" @checked($selectedType === 'file') onchange="toggleTypeFields('file')">
            <label class="form-check-label" for="type_file"><i class="bi bi-file-earmark-text text-primary me-1"></i>Tệp tài liệu (PDF, Word, PPT, Excel)</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="type" id="type_video" value="video" @checked($selectedType === 'video') onchange="toggleTypeFields('video')">
            <label class="form-check-label" for="type_video"><i class="bi bi-play-circle text-danger me-1"></i>Video bài giảng (MP4 hoặc Link YouTube/Drive)</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="type" id="type_link" value="link" @checked($selectedType === 'link') onchange="toggleTypeFields('link')">
            <label class="form-check-label" for="type_link"><i class="bi bi-link-45deg text-info me-1"></i>Liên kết ngoài (URL)</label>
        </div>
    </div>
    @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

<x-form.input name="title" label="Tiêu đề học liệu / tài liệu" :value="$document?->title" required placeholder="Ví dụ: Bài giảng Chương 1 - slide trình chiếu" />

<!-- VÙNG KÉO THẢ TỆP (DRAG AND DROP) -->
<div class="mb-3" id="file_container" style="{{ $selectedType === 'link' ? 'display: none;' : '' }}">
    <label class="form-label fw-semibold">
        {{ $document ? 'Thay tệp tải lên (để trống nếu giữ nguyên)' : 'Tệp tải lên' }}
        @if(! $document && $selectedType === 'file')<span class="text-danger" id="file_required_mark">*</span>@endif
    </label>

    <div id="drop_zone" class="border border-2 border-dashed rounded p-4 text-center bg-light position-relative" style="cursor: pointer; transition: background-color 0.2s, border-color 0.2s;">
        <input type="file" name="file" id="file" class="position-absolute top-0 start-0 w-100 h-100 opacity-0 @error('file') is-invalid @enderror" style="cursor: pointer;" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.mp4,.webm" @if(! $document && $selectedType === 'file') required @endif>
        <div id="drop_zone_prompt">
            <i class="bi bi-cloud-arrow-up display-5 text-primary"></i>
            <div class="fw-semibold mt-2">Kéo thả tệp vào đây hoặc bấm để chọn tệp từ thiết bị</div>
            <div class="text-muted small mt-1" id="file_type_hint">
                Hỗ trợ PDF, Word (.doc, .docx), PowerPoint (.ppt, .pptx), Excel (.xls, .xlsx). Tối đa 50 MB.
            </div>
        </div>
        <div id="file_info_preview" class="d-none text-success fw-semibold mt-2">
            <i class="bi bi-check-circle-fill me-1"></i><span id="file_name_display"></span>
            <span class="badge bg-secondary ms-2" id="file_size_display"></span>
        </div>
    </div>
    @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

    @if($document && $document->file_path)
        <div class="small text-muted mt-2">
            <i class="bi bi-paperclip me-1"></i>Tệp hiện tại: <strong>{{ $document->original_name }}</strong> ({{ $document->formattedSize() }})
        </div>
    @endif
</div>

<!-- ĐỊA CHỈ LIÊN KẾT (URL) -->
<div class="mb-3" id="url_container" style="{{ in_array($selectedType, ['link', 'video']) ? '' : 'display: none;' }}">
    <label for="url" class="form-label fw-semibold">
        Địa chỉ liên kết (URL) <span class="text-danger" id="url_required_mark" style="{{ $selectedType === 'link' ? '' : 'display: none;' }}">*</span>
    </label>
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-globe"></i></span>
        <input type="url" name="url" id="url" class="form-control @error('url') is-invalid @enderror" placeholder="https://www.youtube.com/watch?v=... hoặc https://drive.google.com/..." value="{{ old('url', $document?->url) }}">
    </div>
    @error('url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    <div class="form-text">Dán đường dẫn video YouTube, Google Drive, hoặc liên kết tài liệu trực tuyến.</div>
</div>

<div class="mb-3">
    <label for="lesson_id" class="form-label fw-semibold">Gắn vào bài học</label>
    <select name="lesson_id" id="lesson_id" class="form-select @error('lesson_id') is-invalid @enderror">
        <option value="">Không gắn bài học (Tài liệu chung của khóa)</option>
        @foreach($chapters as $chapter)
            <optgroup label="{{ $chapter->title }}">
                @foreach($chapter->lessons as $lesson)
                    <option value="{{ $lesson->id }}" @selected($selectedLessonId == $lesson->id)>{{ $lesson->title }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    @error('lesson_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

<div class="card bg-light p-3 mb-3">
    <label class="form-label fw-semibold mb-2">Cấu hình quyền học viên</label>
    <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" name="allow_view" id="allow_view" value="1" @checked(old('allow_view', $document?->allow_view ?? true))>
        <label class="form-check-label fw-medium" for="allow_view">
            <i class="bi bi-eye text-success me-1"></i>Cho phép xem trực tuyến
        </label>
        <div class="form-text mt-0">Học viên có thể mở tài liệu xem trên trình duyệt hoặc trình xem tích hợp.</div>
    </div>
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="allow_download" id="allow_download" value="1" @checked(old('allow_download', $document?->allow_download ?? false))>
        <label class="form-check-label fw-medium" for="allow_download">
            <i class="bi bi-download text-primary me-1"></i>Cho phép tải về
        </label>
        <div class="form-text mt-0">Học viên có thể tải tệp gốc về máy tính. Tắt quyền này để chặn tải trực tiếp.</div>
    </div>
</div>

<!-- Thanh tiến trình tải lên (TS2-06) -->
<div id="upload_progress_wrapper" class="mb-3 d-none">
    <label class="form-label small text-muted d-flex justify-content-between">
        <span>Đang xử lý tải lên...</span>
        <span id="upload_progress_percent">0%</span>
    </label>
    <div class="progress" style="height: 8px;">
        <div id="upload_progress_bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
    </div>
</div>

<script>
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
        hint.textContent = 'Hỗ trợ PDF, Word (.doc, .docx), PowerPoint (.ppt, .pptx), Excel (.xls, .xlsx). Tối đa 50 MB.';
        @if(! $document) fileInput.required = true; @endif
    } else if (type === 'video') {
        fileContainer.style.display = 'block';
        urlContainer.style.display = 'block';
        if (fileReqMark) fileReqMark.style.display = 'none';
        if (urlReqMark) urlReqMark.style.display = 'none';
        hint.textContent = 'Tải tệp video (MP4, WebM) tối đa 100 MB hoặc dán link YouTube/Drive bên dưới.';
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

    if (!dropZone || !fileInput) return;

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropZone.classList.add('bg-white', 'border-primary');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropZone.classList.remove('bg-white', 'border-primary');
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
        }
    });

    // Form submit simulated progress
    const form = dropZone.closest('form');
    if (form) {
        form.addEventListener('submit', function () {
            const progressWrapper = document.getElementById('upload_progress_wrapper');
            const progressBar = document.getElementById('upload_progress_bar');
            const progressPercent = document.getElementById('upload_progress_percent');

            if (fileInput.files && fileInput.files.length > 0) {
                progressWrapper.classList.remove('d-none');
                let percent = 10;
                progressBar.style.width = percent + '%';
                progressPercent.textContent = percent + '%';

                const interval = setInterval(() => {
                    if (percent < 90) {
                        percent += Math.floor(Math.random() * 15) + 5;
                        if (percent > 90) percent = 90;
                        progressBar.style.width = percent + '%';
                        progressPercent.textContent = percent + '%';
                    } else {
                        clearInterval(interval);
                    }
                }, 200);
            }
        });
    }
});
</script>
