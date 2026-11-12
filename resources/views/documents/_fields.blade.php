@php($document ??= null)
<x-form.input name="title" label="Tên tài liệu" :value="$document?->title" required />
<div class="mb-3">
    <label for="file" class="form-label">{{ $document ? 'Thay tệp (để trống nếu giữ nguyên)' : 'Tệp tài liệu' }} @if(! $document)<span class="text-accent" aria-hidden="true">*</span>@endif</label>
    <input type="file" name="file" id="file" class="form-control @error('file') is-invalid @enderror" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.mp4,.webm" @if(! $document) required @endif>
    @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    <div class="form-text">PDF, Word, PowerPoint, Excel (tối đa 20 MB) hoặc video MP4/WebM (tối đa 200 MB).</div>
</div>
<div class="mb-3">
    <label for="lesson_id" class="form-label">Gắn vào bài học</label>
    <select name="lesson_id" id="lesson_id" class="form-select @error('lesson_id') is-invalid @enderror">
        <option value="">Không gắn bài học</option>
        @foreach($chapters as $chapter)
            <optgroup label="{{ $chapter->title }}">
                @foreach($chapter->lessons as $lesson)
                    <option value="{{ $lesson->id }}" @selected(old('lesson_id', $document?->lesson_id) == $lesson->id)>{{ $lesson->title }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    @error('lesson_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <div class="form-check"><input class="form-check-input" type="checkbox" name="allow_view" id="allow_view" value="1" @checked(old('allow_view', $document?->allow_view ?? true))><label class="form-check-label" for="allow_view">Cho phép xem trực tuyến</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="allow_download" id="allow_download" value="1" @checked(old('allow_download', $document?->allow_download ?? false))><label class="form-check-label" for="allow_download">Cho phép tải về</label></div>
</div>
