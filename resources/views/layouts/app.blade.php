<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1f5c4d"><title>{{ $title ?? 'Quản lý học liệu' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600&family=JetBrains+Mono:wght@400;600&family=Newsreader:opsz,wght@6..72,500;6..72,650&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    @endif
</head>
<body>
<div class="d-flex min-vh-100">
    <aside class="lm-sidebar d-none d-lg-flex flex-column flex-shrink-0 p-4">
        <a href="{{ route('dashboard') }}" class="text-decoration-none text-white d-flex align-items-center gap-2 mb-5"><span class="lm-brand-mark bg-accent">L</span><span class="fs-5" style="font-family:var(--serif)">LearnMate</span></a>
        <div class="section-label text-uppercase mb-2">Không gian học tập</div>
        <nav class="nav flex-column gap-1">
            <a class="nav-link active" href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2 me-2" aria-hidden="true"></i>Tổng quan</a>
            @if(auth()->user()->hasRole('admin'))<a class="nav-link" href="{{ route('admin.users.index') }}"><i class="bi bi-people me-2" aria-hidden="true"></i>Tài khoản</a><a class="nav-link" href="{{ route('courses.index') }}"><i class="bi bi-journal-text me-2" aria-hidden="true"></i>Khóa học</a>@endif
            @if(auth()->user()->hasRole('teacher'))<a class="nav-link" href="{{ route('teacher.dashboard') }}"><i class="bi bi-journal-text me-2" aria-hidden="true"></i>Khóa học</a><a class="nav-link" href="{{ route('teacher.dashboard') }}"><i class="bi bi-folder2-open me-2" aria-hidden="true"></i>Tài liệu</a><a class="nav-link" href="{{ route('teacher.dashboard') }}"><i class="bi bi-bar-chart me-2" aria-hidden="true"></i>Thống kê</a>@endif
            @if(auth()->user()->hasRole('student'))<a class="nav-link" href="{{ route('student.dashboard') }}"><i class="bi bi-journal-bookmark me-2" aria-hidden="true"></i>Khóa học của tôi</a><a class="nav-link" href="{{ route('student.dashboard') }}"><i class="bi bi-bookmark me-2" aria-hidden="true"></i>Đã lưu</a>@endif
            <a class="nav-link" href="{{ route('password.edit') }}"><i class="bi bi-person-gear me-2" aria-hidden="true"></i>Tài khoản</a>
        </nav>
        <div class="mt-auto pt-5 small text-white-50"><div class="border-top border-light border-opacity-25 pt-3">Thư viện số<br><span class="font-mono">v1.0 / Sprint 01</span></div></div>
    </aside>
    <div class="flex-grow-1 bg-paper">
        <header class="lm-topbar d-flex align-items-center px-3 px-lg-5">
            <button class="btn btn-outline-primary d-lg-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu" aria-label="Mở menu"><i class="bi bi-list" aria-hidden="true"></i></button>
            <div class="small text-muted"><a href="{{ route('dashboard') }}" class="text-decoration-none">LearnMate</a><span class="mx-2">/</span><span>{{ $title ?? 'Tổng quan' }}</span></div>
            <div class="ms-auto d-flex align-items-center gap-3"><button class="btn btn-link text-brand p-2" type="button" data-theme-toggle aria-label="Đổi chế độ sáng tối" aria-pressed="false"><i class="bi bi-moon-stars" aria-hidden="true"></i></button><div class="lm-avatar" aria-label="Tài khoản {{ auth()->user()->name }}">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</div><div class="d-none d-md-block small"><div class="fw-semibold">{{ auth()->user()->name }}</div><div class="text-muted">{{ auth()->user()->role?->display_name }}</div></div><div class="dropdown"><button class="btn btn-link text-brand p-2" data-bs-toggle="dropdown" aria-label="Mở menu người dùng"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="{{ route('password.edit') }}"><i class="bi bi-key me-2"></i>Đổi mật khẩu</a></li><li><hr class="dropdown-divider"></li><li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</button></form></li></ul></div></div>
        </header>
        <main class="lm-main py-4 py-lg-5">@if(session('status'))<x-alert type="success" :message="session('status')" />@endif @if($errors->any())<x-alert>{{ $errors->first() }}</x-alert>@endif @yield('content')</main>
    </div>
</div>
<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel"><div class="offcanvas-header"><h5 id="mobileMenuLabel" class="mb-0" style="font-family:var(--serif)">LearnMate</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Đóng"></button></div><div class="offcanvas-body"><nav class="nav flex-column gap-2"><a class="nav-link text-white" href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2 me-2"></i>Tổng quan</a>@if(auth()->user()->hasRole('admin'))<a class="nav-link text-white" href="{{ route('admin.dashboard') }}"><i class="bi bi-people me-2"></i>Tài khoản</a><a class="nav-link text-white" href="{{ route('courses.index') }}"><i class="bi bi-journal-text me-2"></i>Khóa học</a>@elseif(auth()->user()->hasRole('teacher'))<a class="nav-link text-white" href="{{ route('teacher.dashboard') }}"><i class="bi bi-journal-text me-2"></i>Khóa học</a><a class="nav-link text-white" href="{{ route('teacher.dashboard') }}"><i class="bi bi-folder2-open me-2"></i>Tài liệu</a><a class="nav-link text-white" href="{{ route('teacher.dashboard') }}"><i class="bi bi-bar-chart me-2"></i>Thống kê</a>@else<a class="nav-link text-white" href="{{ route('student.dashboard') }}"><i class="bi bi-journal-bookmark me-2"></i>Khóa học của tôi</a><a class="nav-link text-white" href="{{ route('student.dashboard') }}"><i class="bi bi-bookmark me-2"></i>Đã lưu</a>@endif<a class="nav-link text-white" href="{{ route('password.edit') }}"><i class="bi bi-person-gear me-2"></i>Tài khoản</a></nav></div></div>
</body>
</html>
