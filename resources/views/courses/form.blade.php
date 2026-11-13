@extends($layout)

@section('content')
    <x-page-header
        eyebrow="KHÓA HỌC"
        :title="$course->exists ? 'Chỉnh sửa khóa học' : 'Tạo khóa học mới'"
        description="Nhập thông tin, ảnh bìa và trạng thái của khóa học."
    />

    <div class="row">
        <div class="col-xl-8">
            <x-card class="p-4 p-md-5">
                <form
                    method="POST"
                    action="{{ $course->exists ? route('courses.update', $course) : route('courses.store') }}"
                    enctype="multipart/form-data"
                >
        @csrf
                    @if ($course->exists)
                        @method('PUT')
                    @endif

                    <x-form.input name="title" label="Tên khóa học" :value="$course->title" required />

                    <div class="mb-3">
                        <label for="subject_id" class="form-label">
                            Môn học <span class="text-accent" aria-hidden="true">*</span>
                        </label>
                        <select name="subject_id" id="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                            <option value="">Chọn môn học</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected(old('subject_id', $course->subject_id) == $subject->id)>
                                    {{ $subject->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('subject_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    @if ($teachers->isNotEmpty())
                        <div class="mb-3">
                            <label for="teacher_id" class="form-label">
                                Giảng viên <span class="text-accent" aria-hidden="true">*</span>
                            </label>
                            <select name="teacher_id" id="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror" required>
                                <option value="">Chọn giảng viên</option>
                                @foreach ($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" @selected(old('teacher_id', $course->teacher_id) == $teacher->id)>
                                        {{ $teacher->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('teacher_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea name="description" id="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $course->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Ảnh bìa khóa học</label>
                        <div class="d-flex flex-wrap align-items-center gap-3 p-3 bg-light rounded border">
                            <div class="position-relative border rounded overflow-hidden bg-white shadow-sm flex-shrink-0" style="width: 200px; height: 112px;">
                                <img id="cover_preview" src="{{ $coverImageUrl ?: asset('images/default-course.svg') }}" alt="Ảnh bìa" class="w-100 h-100 object-fit-cover">
                            </div>
                            <div class="flex-grow-1">
                                <div class="small text-muted mb-2">Xem trước ảnh bìa (chuẩn tỷ lệ 16:9). Nếu không tải ảnh, hệ thống sẽ sử dụng ảnh mặc định.</div>
                                <label for="cover_image" class="btn btn-sm btn-outline-primary mb-1 cursor-pointer">
                                    <i class="bi bi-cloud-arrow-up me-1"></i>Tải ảnh lên từ máy tính
                                </label>
                                <input type="file" name="cover_image" id="cover_image" accept=".jpg,.jpeg,.png,.webp" class="d-none @error('cover_image') is-invalid @enderror" onchange="previewCoverImage(this)">
                                @if($coverImageUrl)
                                    <span class="badge bg-success-subtle text-success border ms-2">Đang có ảnh tùy chỉnh</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border ms-2">Đang dùng ảnh mặc định</span>
                                @endif
                                <div class="form-text small mt-1">Định dạng JPG, PNG hoặc WEBP, tối đa 2 MB.</div>
                            </div>
                        </div>
                        @error('cover_image')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">
                            Trạng thái <span class="text-accent" aria-hidden="true">*</span>
                        </label>
                        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach (['draft' => 'Nháp', 'public' => 'Công khai', 'archived' => 'Lưu trữ'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $course->status) === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="start_at" class="form-label">Thời gian bắt đầu</label>
                            <input type="datetime-local" name="start_at" id="start_at" class="form-control @error('start_at') is-invalid @enderror" value="{{ old('start_at', $course->start_at?->format('Y-m-d\\TH:i')) }}">
                            @error('start_at')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="end_at" class="form-label">Thời gian kết thúc</label>
                            <input type="datetime-local" name="end_at" id="end_at" class="form-control @error('end_at') is-invalid @enderror" value="{{ old('end_at', $course->end_at?->format('Y-m-d\\TH:i')) }}">
                            @error('end_at')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="border-top pt-4 mt-2 d-flex gap-2">
                        <x-button>{{ $course->exists ? 'Lưu thay đổi' : 'Tạo khóa học' }}</x-button>
                        <x-button :href="route('courses.index')" variant="outline-primary">Hủy</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
    <script>
        function previewCoverImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('cover_preview');
                    if (preview) {
                        preview.src = e.target.result;
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
@endsection
