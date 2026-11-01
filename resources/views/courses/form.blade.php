@extends('layouts.app')
@section('content')
<x-page-header eyebrow="KHÓA HỌC" :title="$course->exists ? 'Chỉnh sửa khóa học' : 'Tạo khóa học mới'" description="Nhập thông tin, ảnh bìa và trạng thái của khóa học." />
<div class="row"><div class="col-xl-8"><x-card class="p-4 p-md-5">
    <form method="POST" action="{{ $course->exists ? route('courses.update', $course) : route('courses.store') }}" enctype="multipart/form-data">
        @csrf
        @if($course->exists) @method('PUT') @endif
        <x-form.input name="title" label="Tên khóa học" :value="$course->title" required />
        <div class="mb-3">
            <label for="subject_id" class="form-label">Môn học <span class="text-accent" aria-hidden="true">*</span></label>
            <select name="subject_id" id="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                <option value="">Chọn môn học</option>
                @foreach($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected(old('subject_id', $course->subject_id) == $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
            @error('subject_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        @if(auth()->user()->hasRole('admin'))
            <div class="mb-3">
                <label for="teacher_id" class="form-label">Giảng viên <span class="text-accent" aria-hidden="true">*</span></label>
                <select name="teacher_id" id="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror" required>
                    <option value="">Chọn giảng viên</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected(old('teacher_id', $course->teacher_id) == $teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
                @error('teacher_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        @endif
        <div class="mb-3">
            <label for="description" class="form-label">Mô tả</label>
            <textarea name="description" id="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $course->description) }}</textarea>
            @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="cover_image" class="form-label">Ảnh bìa</label>
            @if($course->cover_image)
                <div class="mb-2"><img src="{{ Storage::disk('public')->url($course->cover_image) }}" alt="Ảnh bìa hiện tại" class="img-thumbnail" style="max-height:140px"></div>
            @endif
            <input type="file" name="cover_image" id="cover_image" accept=".jpg,.jpeg,.png,.webp" class="form-control @error('cover_image') is-invalid @enderror">
            @error('cover_image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="form-text">JPG, PNG hoặc WEBP, tối đa 2 MB.</div>
        </div>
        <div class="mb-3">
            <label for="status" class="form-label">Trạng thái <span class="text-accent" aria-hidden="true">*</span></label>
            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                @foreach(['draft' => 'Nháp', 'public' => 'Công khai', 'archived' => 'Lưu trữ'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $course->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="border-top pt-4 mt-2 d-flex gap-2">
            <x-button>{{ $course->exists ? 'Lưu thay đổi' : 'Tạo khóa học' }}</x-button>
            <x-button :href="route('courses.index')" variant="outline-primary">Hủy</x-button>
        </div>
    </form>
</x-card></div></div>
@endsection
