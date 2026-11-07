@extends('layouts.admin')

@section('content')
    <x-page-header
        eyebrow="QUẢN TRỊ / MÔN HỌC"
        :title="$subject->exists ? 'Chỉnh sửa môn học' : 'Thêm môn học mới'"
        description="Môn học là danh mục dùng để phân loại các khóa ôn thi THPT."
    />

    <div class="row">
        <div class="col-xl-8">
            <x-card class="p-4 p-md-5">
                <form method="POST" action="{{ $subject->exists ? route('admin.subjects.update', $subject) : route('admin.subjects.store') }}">
                    @csrf
                    @if($subject->exists)
                        @method('PUT')
                    @endif

                    <x-form.input
                        name="name"
                        label="Tên môn học"
                        :value="$subject->name"
                        placeholder="Ví dụ: Toán"
                        required
                    />

                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea
                            name="description"
                            id="description"
                            rows="5"
                            class="form-control @error('description') is-invalid @enderror"
                            placeholder="Mô tả ngắn về nội dung môn học"
                        >{{ old('description', $subject->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="border-top pt-4 mt-2 d-flex gap-2">
                        <x-button>
                            {{ $subject->exists ? 'Lưu thay đổi' : 'Thêm môn học' }}
                        </x-button>
                        <x-button :href="route('admin.subjects.index')" variant="outline-primary">
                            Hủy
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
