@extends('layouts.guest')

@section('content')
    <div class="min-vh-100 d-flex align-items-center justify-content-center">
        <div class="text-center">
            <div class="font-mono text-accent display-6">403</div>
            <h1 class="display-5">Trang này không mở cho bạn.</h1>
            <p class="lm-text-muted">Tài khoản hiện tại không có quyền truy cập nội dung này.</p>
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary mt-3">
                Quay về nơi an toàn
            </a>
        </div>
    </div>
@endsection
