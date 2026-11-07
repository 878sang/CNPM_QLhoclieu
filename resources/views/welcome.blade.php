<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1f5c4d">
    <title>LearnMate — Hệ thống quản lý học liệu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600&family=JetBrains+Mono:wght@400;600&family=Newsreader:opsz,wght@6..72,500;6..72,650&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @endif
</head>

<body>
    <nav class="navbar bg-surface border-bottom py-3">
        <div class="container">
            <a href="/" class="navbar-brand lm-brand fw-semibold d-flex align-items-center gap-2">
                <span class="lm-brand-mark">L</span>
                <span style="font-family: var(--serif); font-size: 1.3rem">LearnMate</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-link text-brand p-2" data-theme-toggle aria-label="Đổi chế độ sáng tối" aria-pressed="false">
                    <i class="bi bi-moon-stars" aria-hidden="true"></i>
                </button>
                <a href="{{ route('login') }}" class="btn btn-outline-primary">Đăng nhập</a>
            </div>
        </div>
    </nav>

    <main>
        <section class="container py-5">
            <div class="row align-items-center g-5 py-lg-5">
                <div class="col-lg-7 fade-up">
                    <div class="font-mono text-accent small text-uppercase mb-3">THƯ VIỆN SỐ / 01</div>
                    <h1 class="display-2 lh-1 mb-4">Học liệu để <em>đọc sâu</em>,<br>học đúng nhịp.</h1>
                    <div class="lm-underline"></div>
                    <p class="lead lm-text-muted mb-4" style="max-width: 600px">
                        Một không gian tĩnh tại cho giảng viên chia sẻ kiến thức và học viên tự xây dựng hành trình học tập của mình.
                    </p>
                    <a href="{{ route('login') }}" class="btn btn-primary px-4">
                        Bắt đầu học <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
                    </a>
                </div>

                <div class="col-lg-5 fade-up stagger-2">
                    <div class="lm-card p-4 p-md-5">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                            <span class="font-mono small text-muted">HỌC TẬP / 2026</span>
                            <i class="bi bi-book-half text-accent fs-4" aria-hidden="true"></i>
                        </div>
                        <div class="mb-4">
                            <div class="font-mono display-5 text-brand">03</div>
                            <div class="lm-text-muted">môn học đang mở</div>
                        </div>
                        <div class="border-top pt-3 d-flex justify-content-between">
                            <span class="small">Tiến độ tuần này</span>
                            <span class="font-mono small text-brand">78%</span>
                        </div>
                        <div class="progress lm-progress mt-2">
                            <div class="progress-bar" style="width: 78%"></div>
                        </div>
                        <svg class="w-100 mt-5 text-brand" height="72" viewBox="0 0 400 72" fill="none" aria-hidden="true">
                            <path d="M0 54C54 12 86 70 138 34s87-26 124 0 78-8 138-26" stroke="currentColor" />
                            <path d="M0 68h400" stroke="var(--line)" />
                            <circle cx="262" cy="34" r="4" fill="var(--accent)" />
                        </svg>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-top">
            <div class="container py-5">
                <div class="row g-4">
                    @foreach ([
                        ['number' => '01', 'title' => 'Học liệu tập trung', 'description' => 'Tài liệu và khóa học được sắp xếp rõ ràng theo môn học.'],
                        ['number' => '02', 'title' => 'Tiến độ có chủ đích', 'description' => 'Nhìn thấy việc cần làm tiếp theo mà không bị quá tải thông tin.'],
                        ['number' => '03', 'title' => 'Một không gian đáng tin', 'description' => 'Nền tảng dành cho việc đọc, ghi chú và học lâu dài.'],
                    ] as $feature)
                        <div class="col-md-4">
                            <div class="font-mono text-accent mb-3">{{ $feature['number'] }}</div>
                            <h2 class="h4">{{ $feature['title'] }}</h2>
                            <p class="lm-text-muted">{{ $feature['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    <footer class="border-top py-4">
        <div class="container d-flex justify-content-between small lm-text-muted">
            <span>© {{ date('Y') }} LearnMate</span>
            <span class="font-mono">học liệu / kết nối / tiến bộ</span>
        </div>
    </footer>
</body>
</html>
