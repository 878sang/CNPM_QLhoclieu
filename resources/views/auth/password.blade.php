@extends($layout)

@section('content')
    <x-page-header
        eyebrow="TÀI KHOẢN"
        title="Đổi mật khẩu"
        description="Giữ tài khoản của bạn an toàn bằng một mật khẩu riêng tư và dễ quản lý."
    />

    <div class="row g-4">
        <div class="col-xl-7">
            <x-card class="p-4 p-md-5 fade-up">
                <div class="d-flex gap-3 align-items-start mb-4">
                    <span class="lm-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
                    <div>
                        <h2 class="h5 mb-1">Mật khẩu mới</h2>
                        <p class="lm-text-muted small mb-0">Dùng ít nhất 8 ký tự, kết hợp chữ và số.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('password.change') }}">
                    @csrf
                    @method('PUT')
                    <x-form.input name="current_password" label="Mật khẩu hiện tại" type="password" required />
                    <div class="mb-3">
                        <x-form.input
                            name="password"
                            label="Mật khẩu mới"
                            type="password"
                            data-password-strength="#password-strength"
                            required
                        />
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <div class="progress lm-progress flex-grow-1">
                                <div id="password-strength" class="progress-bar" style="width: 0%"></div>
                            </div>
                            <span id="password-strength-label" class="font-mono text-muted small">Chưa có</span>
                        </div>
                    </div>
                    <x-form.input name="password_confirmation" label="Xác nhận mật khẩu mới" type="password" required />
                    <x-button class="mt-2">Lưu mật khẩu mới</x-button>
                </form>
            </x-card>
        </div>

        <div class="col-xl-4">
            <x-card class="p-4 fade-up stagger-2">
                <i class="bi bi-lock fs-3 text-brand" aria-hidden="true"></i>
                <h2 class="h5 mt-3">Một vài nguyên tắc</h2>
                <ul class="small lm-text-muted ps-3 mb-0">
                    <li class="mb-2">Không dùng lại mật khẩu ở nhiều nơi.</li>
                    <li class="mb-2">Không chia sẻ mật khẩu qua tin nhắn.</li>
                    <li>Đổi mật khẩu ngay nếu nghi ngờ bị lộ.</li>
                </ul>
            </x-card>
        </div>
    </div>
@endsection
