@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')

@section('content')
<x-page-header
    eyebrow="QUẢN LÝ THÔNG BÁO"
    title="Gửi thông báo mới"
    description="Tạo và gửi thông báo tới toàn hệ thống, khóa học hoặc cá nhân học viên."
>
    <x-slot:actions>
        <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
        </a>
    </x-slot:actions>
</x-page-header>

@if ($errors->any())
    <x-alert type="danger" class="mb-3">{{ $errors->first() }}</x-alert>
@endif

<x-card class="p-4" style="max-width: 780px;">
    <form method="POST" action="{{ route('notifications.store') }}">
        @csrf

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label small fw-semibold" for="target_type">Đối tượng nhận <span class="text-danger">*</span></label>
                <select name="target_type" id="target_type" class="form-select">
                    @if($isAdmin)
                        <option value="all" @selected(old('target_type') === 'all')>Toàn bộ người dùng hệ thống</option>
                        <option value="role" @selected(old('target_type') === 'role')>Theo vai trò (Học viên / Giảng viên)</option>
                    @endif
                    <option value="course" @selected(old('target_type', $isAdmin ? '' : 'course') === 'course')>Tất cả học viên trong khóa học</option>
                    <option value="user" @selected(old('target_type') === 'user')>Cá nhân cụ thể</option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label small fw-semibold" for="notif_type">Danh mục thông báo <span class="text-danger">*</span></label>
                <select name="type" id="notif_type" class="form-select">
                    @foreach(\App\Models\AppNotification::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected(old('type', 'course') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($isAdmin)
        <div class="mb-3 d-none" id="targetRoleGroup">
            <label class="form-label small fw-semibold" for="target_role">Chọn vai trò</label>
            <select name="target_role" id="target_role" class="form-select">
                <option value="student" @selected(old('target_role') === 'student')>Tất cả Học viên</option>
                <option value="teacher" @selected(old('target_role') === 'teacher')>Tất cả Giảng viên</option>
                <option value="admin" @selected(old('target_role') === 'admin')>Tất cả Quản trị viên</option>
            </select>
        </div>
        @endif

        <div class="mb-3 d-none" id="targetCourseGroup">
            <label class="form-label small fw-semibold" for="course_id">Chọn khóa học</label>
            <select name="course_id" id="course_id" class="form-select">
                <option value="">-- Chọn khóa học --</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id }}" @selected(old('course_id') == $c->id)>
                        {{ $c->title }} ({{ $c->enrollments_count }} học viên)
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3 d-none" id="targetUserGroup">
            <label class="form-label small fw-semibold" for="recipient_id">Chọn người nhận</label>
            <select name="recipient_id" id="recipient_id" class="form-select">
                <option value="">-- Chọn người nhận --</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(old('recipient_id') == $u->id)>
                        {{ $u->name }} ({{ $u->email }}) — {{ $u->role?->display_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold" for="title">Tiêu đề thông báo <span class="text-danger">*</span></label>
            <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}" placeholder="VD: Thông báo lịch kiểm tra giữa kỳ" required>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold" for="message">Nội dung thông báo <span class="text-danger">*</span></label>
            <textarea name="message" id="message" rows="5" class="form-control" placeholder="Nhập nội dung chi tiết thông báo..." required>{{ old('message') }}</textarea>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-semibold" for="action_url">Đường dẫn đính kèm (tùy chọn)</label>
            <input type="text" name="action_url" id="action_url" class="form-control" value="{{ old('action_url') }}" placeholder="VD: /hoc-vien/khoa-hoc/1">
            <div class="form-text small">Khi người nhận bấm vào thông báo sẽ tự động chuyển đến đường dẫn này.</div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary">Hủy</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold">
                <i class="bi bi-send me-1"></i>Gửi thông báo
            </button>
        </div>
    </form>
</x-card>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const targetType = document.getElementById('target_type');
    const roleGroup = document.getElementById('targetRoleGroup');
    const courseGroup = document.getElementById('targetCourseGroup');
    const userGroup = document.getElementById('targetUserGroup');

    function updateTargetFields() {
        const val = targetType.value;
        if (roleGroup) roleGroup.classList.toggle('d-none', val !== 'role');
        if (courseGroup) courseGroup.classList.toggle('d-none', val !== 'course');
        if (userGroup) userGroup.classList.toggle('d-none', val !== 'user');
    }

    targetType.addEventListener('change', updateTargetFields);
    updateTargetFields();
});
</script>
@endpush
@endsection
