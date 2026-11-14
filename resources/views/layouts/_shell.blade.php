@php($role = $role ?? auth()->user()->role?->name ?? 'student')

<!doctype html>
<html lang="vi" data-role="{{ $role }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Quản lý học liệu' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600&family=JetBrains+Mono:wght@400;600&family=Newsreader:opsz,wght@6..72,500;6..72,650&display=swap" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    @endif
</head>
<body class="role-{{ $role }}">
    @if ($role === 'student')
        <header class="student-topbar">
            <a href="{{ route('student.dashboard') }}" class="student-logo">
                <span class="lm-brand-mark">L</span>
                <span>LearnMate</span>
            </a>

            <nav class="student-nav">
                <a class="{{ request()->routeIs('student.dashboard', 'student.courses') ? 'active' : '' }}" href="{{ route('student.courses') }}">
                    Khóa học của tôi
                </a>
                <a class="{{ request()->routeIs('student.search') ? 'active' : '' }}" href="{{ route('student.search') }}">
                    Tìm học liệu
                </a>
                <a class="{{ request()->routeIs('student.progress') ? 'active' : '' }}" href="{{ route('student.progress') }}">
                    Tiến độ
                </a>
                <a class="{{ request()->routeIs('student.saved') ? 'active' : '' }}" href="{{ route('student.saved') }}">
                    Đã lưu
                </a>
            </nav>

            <div class="student-tools">
                <button class="btn btn-link text-brand p-2" type="button" onclick="window.location='{{ route('student.search') }}'" aria-label="Tìm kiếm">
                    <i class="bi bi-search"></i>
                </button>
                <button class="btn btn-link text-brand p-2" type="button" aria-label="Thông báo">
                    <i class="bi bi-bell"></i>
                </button>
                <div class="dropdown">
                    <button class="btn btn-link p-0" data-bs-toggle="dropdown" aria-label="Mở menu tài khoản">
                        <span class="lm-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('password.edit') }}">Đổi mật khẩu</a></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item text-danger">Đăng xuất</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
    @else
        <div class="role-shell">
            <aside class="lm-sidebar d-none d-lg-flex flex-column flex-shrink-0 p-4">
                <a href="{{ route('dashboard') }}" class="text-decoration-none text-white d-flex align-items-center gap-2 mb-4">
                    <span class="lm-brand-mark">L</span>
                    <span class="fs-5" style="font-family: var(--serif)">LearnMate</span>
                </a>
                <div class="role-label mb-4">{{ $role === 'admin' ? 'QUẢN TRỊ' : 'GIẢNG VIÊN' }}</div>

                <nav class="nav flex-column gap-1">
                    @if ($role === 'admin')
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <i class="bi bi-grid-1x2 me-2"></i>Tổng quan
                        </a>
                        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                            <i class="bi bi-people me-2"></i>Người dùng
                        </a>
                        <a class="nav-link {{ request()->routeIs('admin.subjects.*') ? 'active' : '' }}" href="{{ route('admin.subjects.index') }}">
                            <i class="bi bi-book me-2"></i>Môn học
                        </a>
                        <a class="nav-link {{ request()->routeIs('courses.*') ? 'active' : '' }}" href="{{ route('courses.index') }}">
                            <i class="bi bi-journal-text me-2"></i>Khóa học
                        </a>
                        <a class="nav-link {{ request()->routeIs('admin.documents.*') ? 'active' : '' }}" href="{{ route('admin.documents.index') }}">
                            <i class="bi bi-patch-check me-2"></i>Duyệt bài tập
                            <i class="bi bi-folder2-open me-2"></i>Tài liệu
                        </a>
                    @else
                        <a class="nav-link {{ request()->routeIs('teacher.dashboard', 'courses.*') && !request()->routeIs('teacher.materials.*') ? 'active' : '' }}" href="{{ route('teacher.dashboard') }}">
                            <i class="bi bi-journal-text me-2"></i>Khóa học của tôi
                        </a>
                        <a class="nav-link {{ request()->routeIs('teacher.materials.*') ? 'active' : '' }}" href="{{ route('teacher.materials.index') }}">
                            <i class="bi bi-folder2-open me-2"></i>Tài liệu
                        </a>
                        <a class="nav-link {{ request()->routeIs('teacher.students') ? 'active' : '' }}" href="{{ route('teacher.students') }}">
                            <i class="bi bi-people me-2"></i>Học viên
                        </a>
                        <a class="nav-link {{ request()->routeIs('teacher.statistics') ? 'active' : '' }}" href="{{ route('teacher.statistics') }}">
                            <i class="bi bi-bar-chart me-2"></i>Thống kê
                        </a>
                    @endif

                    <a class="nav-link {{ request()->routeIs('password.*') ? 'active' : '' }}" href="{{ route('password.edit') }}">
                        <i class="bi bi-person-gear me-2"></i>Tài khoản
                    </a>
                </nav>

                <div class="mt-auto small text-white-50">
                    Thư viện số<br>
                </div>
            </aside>

            <div class="role-main">
                <header class="lm-topbar d-flex align-items-center px-3 px-lg-5">
                    <button class="btn btn-outline-primary d-lg-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#roleMobileMenu" aria-label="Mở menu">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="small text-muted">
                        <a href="{{ route('dashboard') }}" class="text-decoration-none">LearnMate</a>
                        <span class="mx-2">/</span>{{ $title ?? 'Tổng quan' }}
                    </div>
                    <div class="ms-auto d-flex align-items-center gap-3">
                        <button class="btn btn-link text-brand p-2" type="button" data-theme-toggle aria-label="Đổi chế độ sáng tối">
                            <i class="bi bi-moon-stars"></i>
                        </button>
                        <span class="lm-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <div class="d-none d-md-block small">
                            <div class="fw-semibold">{{ auth()->user()->name }}</div>
                            <div class="text-muted">{{ auth()->user()->role?->display_name }}</div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-link text-danger p-2" aria-label="Đăng xuất">
                                <i class="bi bi-box-arrow-right"></i>
                            </button>
                        </form>
                    </div>
                </header>
    @endif

    <main class="lm-main py-4 py-lg-5">
        @if (session('status'))
            <x-alert type="success" :message="session('status')" />
        @endif
        @if ($errors->any())
            <x-alert>{{ $errors->first() }}</x-alert>
        @endif
        @yield('content')
    </main>

    @if ($role === 'student')
        <nav class="student-bottom-nav">
            <a class="{{ request()->routeIs('student.dashboard', 'student.courses') ? 'active' : '' }}" href="{{ route('student.courses') }}">
                <i class="bi bi-journal-bookmark"></i><span>Học tập</span>
            </a>
            <a class="{{ request()->routeIs('student.search') ? 'active' : '' }}" href="{{ route('student.search') }}">
                <i class="bi bi-search"></i><span>Tìm kiếm</span>
            </a>
            <a class="{{ request()->routeIs('student.progress') ? 'active' : '' }}" href="{{ route('student.progress') }}">
                <i class="bi bi-bar-chart"></i><span>Tiến độ</span>
            </a>
            <a class="{{ request()->routeIs('student.saved') ? 'active' : '' }}" href="{{ route('student.saved') }}">
                <i class="bi bi-bookmark"></i><span>Đã lưu</span>
            </a>
        </nav>
    @else
            </div>
        </div>
        <div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="roleMobileMenu">
            <div class="offcanvas-header">
                <h5 class="mb-0">{{ $role === 'admin' ? 'Khu vực quản trị' : 'Không gian giảng viên' }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <p class="small text-white-50">{{ auth()->user()->name }}</p>
                <nav class="nav flex-column gap-2">
                    @if ($role === 'admin')
                        <a class="nav-link text-white" href="{{ route('admin.dashboard') }}">Tổng quan</a>
                        <a class="nav-link text-white" href="{{ route('admin.users.index') }}">Người dùng</a>
                        <a class="nav-link text-white" href="{{ route('admin.subjects.index') }}">Môn học</a>
                        <a class="nav-link text-white" href="{{ route('courses.index') }}">Khóa học</a>
                        <a class="nav-link text-white" href="{{ route('admin.documents.index') }}">Tài liệu</a>
                    @else
                        <a class="nav-link text-white" href="{{ route('teacher.dashboard') }}">Khóa học của tôi</a>
                        <a class="nav-link text-white" href="{{ route('teacher.materials.index') }}">Tài liệu</a>
                        <a class="nav-link text-white" href="{{ route('teacher.students') }}">Học viên</a>
                        <a class="nav-link text-white" href="{{ route('teacher.statistics') }}">Thống kê</a>
                    @endif
                </nav>
            </div>
        </div>
    @endif
    @stack('scripts')
    </body>
</html>
