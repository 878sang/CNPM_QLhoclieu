<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->title }} — Xem tài liệu trực tuyến</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Thư viện docx-preview kết xuất Word trực tiếp trên trình duyệt -->
    <script src="https://cdn.jsdelivr.net/npm/docx-preview@0.3.3/dist/docx-preview.min.js"></script>

    <style>
        body {
            font-family: 'Be Vietnam Pro', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #eef2f6;
            margin: 0;
            padding: 0;
        }

        .viewer-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #ffffff;
            border-bottom: 1px solid #dee2e6;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }

        .document-canvas {
            max-width: 900px;
            margin: 2rem auto;
            background: #ffffff;
            padding: 3.5rem 3rem;
            border-radius: 6px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            min-height: 1000px;
        }

        .docx-preview-wrapper {
            background: transparent !important;
            padding: 0 !important;
        }

        .docx-wrapper {
            background: transparent !important;
            padding: 0 !important;
        }

        .docx-wrapper > section.docx {
            margin-bottom: 2rem !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06) !important;
        }

        .slide-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .slide-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 0.75rem 1.25rem;
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .slide-body {
            padding: 2rem;
            min-height: 300px;
            font-size: 1.1rem;
            line-height: 1.8;
        }
    </style>
</head>
<body>

    <!-- THANH CÔNG CỤ TRÊN CÙNG (TOOLBAR) -->
    <header class="viewer-header">
        <div class="d-flex align-items-center gap-3">
            <button type="button" onclick="window.history.back()" class="btn btn-outline-secondary btn-sm" title="Quay lại">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </button>
            <div class="vr" style="height: 24px;"></div>
            <div class="d-flex align-items-center gap-2">
                <i class="bi {{ $document->typeIcon() }} fs-4"></i>
                <div>
                    <h1 class="h6 mb-0 fw-bold text-dark">{{ $document->title }}</h1>
                    <div class="small text-muted">
                        {{ $document->original_name }} · {{ $document->formattedSize() }} · Khóa học: <strong>{{ $course->title }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark border d-none d-md-inline-block">
                {{ $document->typeLabel() }}
            </span>

            @if($document->allow_download || auth()->user()->can('update', $course))
                <a href="{{ route('courses.documents.download', [$course, $document]) }}" class="btn btn-success btn-sm">
                    <i class="bi bi-download me-1"></i>Tải về tệp gốc
                </a>
            @endif
        </div>
    </header>

    <!-- NỘI DUNG TÀI LIỆU -->
    <main class="container-fluid py-4">
        @if($document->isWord())
            <div class="document-canvas">
                <div class="d-flex justify-content-between align-items-center pb-3 mb-4 border-bottom">
                    <div class="small text-muted">
                        <i class="bi bi-file-earmark-word text-primary me-1"></i>Trình đọc tài liệu Word trực tuyến
                    </div>
                    <span id="render-badge" class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        <span class="spinner-border spinner-border-sm me-1" id="loading-spinner"></span>Đang tải trang Word...
                    </span>
                </div>

                <!-- Vùng kết xuất pixel-perfect từ docx-preview -->
                <div id="docx-perfect-container"></div>

                <!-- Vùng hiển thị HTML trích xuất từ server (hiện tức thì không cần chờ) -->
                <div id="docx-server-container">
                    @if($extractedHtml)
                        {!! $extractedHtml !!}
                    @else
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-text display-4 d-block mb-3 text-secondary"></i>
                            <p>Không thể đọc trước nội dung văn bản này. Vui lòng tải về để xem đầy đủ.</p>
                        </div>
                    @endif
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const rawUrl = "{{ route('courses.documents.show', [$course, $document, 'raw' => 1]) }}";
                    const perfectContainer = document.getElementById('docx-perfect-container');
                    const serverContainer = document.getElementById('docx-server-container');
                    const badge = document.getElementById('render-badge');
                    const spinner = document.getElementById('loading-spinner');

                    // Gọi docx-preview kết xuất pixel-perfect trực tiếp từ binary file
                    if (window.docx) {
                        fetch(rawUrl)
                            .then(response => {
                                if (!response.ok) throw new Error('Không thể tải tệp');
                                return response.blob();
                            })
                            .then(blob => {
                                return docx.renderAsync(blob, perfectContainer, null, {
                                    className: 'docx-preview-wrapper',
                                    inWrapper: false,
                                    ignoreWidth: false,
                                    ignoreHeight: false,
                                    renderHeaders: true,
                                    renderFooters: true
                                });
                            })
                            .then(() => {
                                // Kết xuất thành công -> ẩn server fallback, hiển thị bản pixel-perfect
                                serverContainer.style.display = 'none';
                                badge.className = 'badge bg-success-subtle text-success border border-success-subtle';
                                badge.innerHTML = '<i class="bi bi-check-circle me-1"></i>Đã hiển thị định dạng Word gốc';
                            })
                            .catch(err => {
                                console.warn('docx-preview fallback to server HTML:', err);
                                // Fallback: giữ nguyên bản HTML server đã trích xuất
                                if (spinner) spinner.style.display = 'none';
                                badge.className = 'badge bg-light text-muted border';
                                badge.innerHTML = '<i class="bi bi-file-text me-1"></i>Bản đọc trực tuyến';
                            });
                    } else {
                        if (spinner) spinner.style.display = 'none';
                        badge.className = 'badge bg-light text-muted border';
                        badge.innerHTML = '<i class="bi bi-file-text me-1"></i>Bản đọc trực tuyến';
                    }
                });
            </script>

        @elseif($document->isPowerPoint())
            <div class="container" style="max-width: 900px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0 text-dark">
                        <i class="bi bi-file-earmark-ppt text-danger me-2"></i>Các slide trình chiếu ({{ count($pptSlides) }} slide)
                    </h2>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">PowerPoint Presentation</span>
                </div>

                @forelse($pptSlides as $slide)
                    <div class="slide-card">
                        <div class="slide-header">
                            <span><i class="bi bi-display me-2 text-danger"></i>Slide {{ $slide['slide_number'] }}</span>
                            <span class="small text-muted">{{ count($slide['content']) }} đoạn nội dung</span>
                        </div>
                        <div class="slide-body">
                            @foreach($slide['content'] as $para)
                                <p class="mb-3">{{ $para }}</p>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="document-canvas text-center py-5">
                        <i class="bi bi-file-earmark-ppt display-4 d-block mb-3 text-secondary"></i>
                        <p class="text-muted">Không tìm thấy slide văn bản hoặc tệp định dạng cũ. Vui lòng tải về máy để xem.</p>
                    </div>
                @endforelse
            </div>

        @else
            <!-- Các tệp khác -->
            <div class="document-canvas text-center py-5">
                <i class="bi {{ $document->typeIcon() }} display-3 d-block mb-3 text-primary"></i>
                <h2 class="h5 fw-bold mb-2">{{ $document->title }}</h2>
                <p class="text-muted mb-4">{{ $document->original_name }} ({{ $document->formattedSize() }})</p>
                @if($document->allow_download || auth()->user()->can('update', $course))
                    <a href="{{ route('courses.documents.download', [$course, $document]) }}" class="btn btn-primary px-4 py-2">
                        <i class="bi bi-download me-2"></i>Tải về tệp về máy tính
                    </a>
                @endif
            </div>
        @endif
    </main>

</body>
</html>

