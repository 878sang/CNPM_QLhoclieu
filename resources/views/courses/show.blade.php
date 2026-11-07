@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')

@section('content')
    <x-page-header
        eyebrow="KHÓA HỌC / CHI TIẾT"
        :title="$course->title"
        description="Thông tin chi tiết và quản lý khóa học."
    >
        <x-slot:actions>
            <x-button :href="route('courses.edit', $course)" variant="outline-secondary">Sửa khóa học</x-button>
            <x-button :href="route('courses.index')" variant="outline-primary">Quay lại</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-8">
            <x-card class="p-4 h-100">
                <x-badge tone="primary">{{ $course->subject?->name }}</x-badge>
                <h1 class="h3 mt-3">{{ $course->title }}</h1>
                <p class="text-muted">{{ $course->description ?: 'Khóa học chưa có mô tả.' }}</p>
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card class="p-4">
                <h2 class="h5">Thông tin khóa học</h2>
                <dl class="row mb-0">
                    <dt class="col-5">Giảng viên</dt>
                    <dd class="col-7">{{ $course->teacher?->name ?: 'Chưa cập nhật' }}</dd>
                    <dt class="col-5">Bắt đầu</dt>
                    <dd class="col-7">{{ $course->start_at?->format('d/m/Y H:i') ?: 'Chưa cập nhật' }}</dd>
                    <dt class="col-5">Kết thúc</dt>
                    <dd class="col-7">{{ $course->end_at?->format('d/m/Y H:i') ?: 'Chưa cập nhật' }}</dd>
                    <dt class="col-5">Học viên</dt>
                    <dd class="col-7">{{ $course->enrollments()->count() }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
