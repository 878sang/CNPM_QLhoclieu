@php
    $document ??= null;
    $selectedType = old('type', $document?->type ?? 'file');
    $selectedLessonId = old('lesson_id', $document?->lesson_id ?? request()->query('lesson_id'));
    $isEdit = (bool) $document;
@endphp

<div class="mb-3"><label class="form-label fw-semibold">Loại học liệu <span class="text-danger">*</span></label>@if($isEdit)<input type="hidden" name="type" value="{{ $selectedType }}"> <div class="small text-muted mb-2"><i class="bi bi-lock me-1"></i>Loại học liệu không thể thay đổi khi sửa.</div>@endif<div class="document-type-options"><label class="form-check"><input class="form-check-input" type="radio" name="type" value="file" @checked($selectedType === 'file') @disabled($isEdit) onchange="toggleDocumentType('file')"> <i class="bi bi-file-earmark-text text-primary me-1"></i>Tệp tài liệu (PDF, Word, PPT, Excel)</label><label class="form-check"><input class="form-check-input" type="radio" name="type" value="video" @checked($selectedType === 'video') @disabled($isEdit) onchange="toggleDocumentType('video')"> <i class="bi bi-play-circle text-danger me-1"></i>Video bài giảng (MP4 hoặc liên kết)</label><label class="form-check"><input class="form-check-input" type="radio" name="type" value="link" @checked($selectedType === 'link') @disabled($isEdit) onchange="toggleDocumentType('link')"> <i class="bi bi-link-45deg text-info me-1"></i>Liên kết ngoài (URL)</label></div>@error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>

<div class="mb-3"><label for="document_title" class="form-label fw-semibold">Tiêu đề học liệu</label><input type="text" name="title" id="document_title" class="form-control" value="{{ old('title', $document?->title) }}" placeholder="Ví dụ: Bài giảng Chương 1"></div>

<div class="mb-3" id="document_file_container" style="{{ $selectedType === 'link' ? 'display:none' : '' }}"><label class="form-label fw-semibold">{{ $isEdit ? 'Thay tệp tải lên (để trống nếu giữ nguyên)' : 'Tệp tải lên' }} @if(!$isEdit && $selectedType === 'file')<span class="text-danger">*</span>@endif</label><label class="document-dropzone" for="document_file"><input type="file" name="file" id="document_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.mp4,.webm,.ogg,.mkv,.mov" @if(!$isEdit && $selectedType === 'file') required @endif><i class="bi bi-cloud-arrow-up display-6 text-primary"></i><span class="fw-semibold">Chọn tệp từ máy tính</span><small class="text-muted" id="document_file_hint">PDF, Word, PowerPoint, Excel tối đa 20 MB; video tối đa 500 MB.</small></label>@if($document?->file_path)<div class="small text-muted mt-2"><i class="bi bi-paperclip me-1"></i>Tệp hiện tại: <strong>{{ $document->original_name }}</strong> ({{ $document->formattedSize() }})</div>@endif @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>

<div class="mb-3" id="document_url_container" style="{{ in_array($selectedType, ['link', 'video']) ? '' : 'display:none' }}"><label for="document_url" class="form-label fw-semibold">Địa chỉ liên kết (URL) <span class="text-danger" id="document_url_required" style="{{ $selectedType === 'link' ? '' : 'display:none' }}">*</span></label><input type="url" name="url" id="document_url" class="form-control" value="{{ old('url', $document?->url) }}" placeholder="https://www.youtube.com/watch?v=... hoặc https://drive.google.com/..."><div class="form-text">Dùng cho video liên kết hoặc tài liệu trực tuyến.</div>@error('url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>

<div class="mb-3"><label class="form-label fw-semibold">Gắn vào bài học</label><select name="lesson_id" class="form-select"><option value="">Không gắn bài học (tài liệu chung của khóa)</option>@foreach($chapters as $chapter)<optgroup label="{{ $chapter->title }}">@foreach($chapter->lessons as $lesson)<option value="{{ $lesson->id }}" @selected($selectedLessonId == $lesson->id)>{{ $lesson->title }}</option>@endforeach</optgroup>@endforeach</select>@error('lesson_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>

<div class="card bg-light border p-3 mb-3"><label class="form-label fw-semibold mb-2">Quyền học viên</label><label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="allow_view" value="1" @checked(old('allow_view', $document?->allow_view ?? true))> Cho phép xem trực tuyến</label><label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="allow_download" value="1" @checked(old('allow_download', $document?->allow_download ?? true))> Cho phép tải về máy tính</label></div>

<script>
function toggleDocumentType(type) {
    const file = document.getElementById('document_file_container');
    const url = document.getElementById('document_url_container');
    const fileInput = document.getElementById('document_file');
    const urlInput = document.getElementById('document_url');
    const urlRequired = document.getElementById('document_url_required');
    file.style.display = type === 'link' ? 'none' : '';
    url.style.display = type === 'file' ? 'none' : '';
    fileInput.required = type === 'file' && {{ $isEdit ? 'false' : 'true' }};
    urlInput.required = type === 'link';
    urlRequired.style.display = type === 'link' ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', () => toggleDocumentType(document.querySelector('input[name="type"]:checked')?.value || 'file'));
</script>
