@extends('layouts.student')

@section('content')
    <x-page-header
        eyebrow="HỌC TẬP / CHI TIẾT KHÓA HỌC"
        :title="$course->title"
        description="Theo dõi nội dung bài học, học liệu và tiến độ học tập cá nhân."
    >
        <x-slot:actions>
            @if ($isEnrolled)
                <x-button :href="route('student.courses.learn', [$course, $lastLesson?->id])" variant="primary">
                    <i class="bi bi-play-circle-fill me-1" aria-hidden="true"></i>{{ $percentage > 0 ? 'Tiếp tục học' : 'Bắt đầu học ngay' }}
                </x-button>
            @endif
            <x-button :href="route('student.courses')" variant="outline-secondary">Quay lại khóa học</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <x-alert tone="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <x-card class="p-4 h-100">
                <div class="row g-3 align-items-center mb-4">
                    <div class="col-md-5">
                        <div class="border rounded overflow-hidden shadow-sm" style="max-height: 180px;">
                            <img src="{{ $course->cover_url }}" alt="{{ $course->title }}" class="w-100 h-100 object-fit-cover">
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <x-badge tone="primary">{{ $course->subject?->name }}</x-badge>
                            @if($isEnrolled)
                                <x-badge :tone="$course->progressStatusTone(auth()->user())">
                                    {{ $course->progressStatusLabel(auth()->user()) }}
                                </x-badge>
                            @endif
                        </div>
                        <h1 class="h4 mb-2 fw-bold text-dark">{{ $course->title }}</h1>
                        <p class="text-muted small mb-0">{{ $course->description ?: 'Khóa học chưa có mô tả.' }}</p>
                    </div>
                </div>

                @if($isEnrolled)
                    <div class="p-3 bg-light rounded border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold small"><i class="bi bi-bar-chart-fill text-primary me-1"></i>Tiến độ học tập của bạn</span>
                            <span class="fw-bold text-primary">{{ $percentage }}%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped bg-success" role="progressbar" style="width: {{ $percentage }}%" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        @if($lastLesson)
                            <div class="small text-muted mt-2">
                                <i class="bi bi-bookmark-fill text-secondary me-1"></i>Đang học dở: <strong>{{ $lastLesson->chapter?->title }} - {{ $lastLesson->title }}</strong>
                            </div>
                        @endif
                    </div>
                @endif
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
                    <dt class="col-5">Tiến độ</dt>
                    <dd class="col-7">
                        <div class="progress lm-progress mb-1" role="progressbar" aria-label="Tiến độ khóa học" aria-valuenow="{{ $progressPercent }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ $progressPercent }}%"></div></div>
                        {{ $isEnrolled ? $progressPercent.'% hoàn thành' : 'Chưa tham gia' }}
                    </dd>
                </dl>
            </x-card>
            <x-card class="p-4 h-100">
                <h2 class="h5 mb-3">Thông tin khóa học</h2>
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Giảng viên</dt>
                    <dd class="col-7 fw-semibold">{{ $course->teacher?->name ?: 'Chưa cập nhật' }}</dd>
                    <dt class="col-5 text-muted">Bắt đầu</dt>
                    <dd class="col-7">{{ $course->start_at?->format('d/m/Y') ?: 'Không giới hạn' }}</dd>
                    <dt class="col-5 text-muted">Kết thúc</dt>
                    <dd class="col-7">{{ $course->end_at?->format('d/m/Y') ?: 'Không giới hạn' }}</dd>
                    <dt class="col-5 text-muted">Số chương</dt>
                    <dd class="col-7">{{ $course->chapters->count() }} chương</dd>
                    <dt class="col-5 text-muted">Số bài học</dt>
                    <dd class="col-7">{{ $course->chapters->sum(fn ($c) => $c->lessons->count()) }} bài học</dd>
                </dl>

                @if ($isEnrolled)
                    <div class="d-grid gap-2 mt-4">
                        <a href="{{ route('student.courses.learn', [$course, $lastLesson?->id]) }}" class="btn btn-primary">
                            <i class="bi bi-play-fill me-1"></i>{{ $percentage > 0 ? 'Tiếp tục học dở' : 'Bắt đầu học ngay' }}
                        </a>
                        <form method="POST" action="{{ route('student.courses.leave', $course) }}" onsubmit="return confirm('Bạn có chắc muốn rời khỏi khóa học này?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger w-100 btn-sm">Rời khóa học</button>
                        </form>
                    </div>
                @else
                    <div class="alert alert-info mt-4 mb-0 small">Bạn chưa tham gia khóa học này.</div>
                @endif
            </x-card>
        </div>
    </div>
    @if ($isEnrolled)
        @php
            $videos = $documents->filter(fn ($document) => $document->isVideo());
        @endphp
        @if ($videos->isNotEmpty())
            <x-card class="p-4 mt-4">
                <h2 class="h5 mb-3">Video bài giảng</h2>
                @foreach ($videos as $video)
                    @php
                        $progress = $progressByDocument[$video->id] ?? null;
                    @endphp
                    <div class="mb-4" @if ($video->allow_view) data-video-progress @endif>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-semibold">{{ $video->title }}</div>
                            @if ($video->allow_view)
                                <span class="small text-muted" data-progress-text>{{ $progress?->completed_at ? 'Đã hoàn thành' : ($progress ? 'Đã xem '.$progress->percent.'%' : 'Chưa xem') }}</span>
                            @endif
                        </div>
                        @if ($video->allow_view)
                            <video class="w-100 rounded bg-dark" style="max-height: 360px" controls preload="metadata" @unless ($video->allow_download) controlsList="nodownload" oncontextmenu="return false" @endunless
                                src="{{ route('courses.documents.show', [$course, $video]) }}"
                                data-progress-url="{{ route('student.documents.progress', $video) }}"
                                data-token="{{ csrf_token() }}"
                                data-resume="{{ $progress && ! $progress->completed_at ? $progress->position_seconds : 0 }}"></video>
                            <div class="progress lm-progress mt-2" role="progressbar" aria-label="Tiến độ {{ $video->title }}" aria-valuenow="{{ $progress?->percent ?? 0 }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" data-progress-bar style="width: {{ $progress?->percent ?? 0 }}%"></div></div>
                        @else
                            <p class="text-muted small mb-2">Video này không cho phép xem trực tuyến.</p>
                        @endif
                        @if ($video->allow_download)
                            <x-button :href="route('courses.documents.download', [$course, $video])" variant="outline-secondary" class="btn-sm mt-2"><i class="bi bi-download me-1" aria-hidden="true"></i>Tải video</x-button>
                        @endif
                    </div>
                @endforeach
            </x-card>
        @endif
        <x-card class="p-4 mt-4">
            <h2 class="h5 mb-3">Bài tập &amp; tài liệu</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Tên bài tập</th><th class="d-none d-md-table-cell">Bài học</th><th>Tiến độ</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody>
                        @php
                            $nonVideoDocs = $documents->reject(fn ($document) => $document->isVideo());
                        @endphp
                        @forelse ($nonVideoDocs as $document)
                            <tr>
                                <td><div class="fw-semibold">{{ $document->title }}</div><div class="small text-muted">{{ $document->original_name }} · {{ number_format($document->size / 1024, 0, ',', '.') }} KB</div></td>
                                <td class="d-none d-md-table-cell">{{ $document->lesson ? $document->lesson->chapter->title.' / '.$document->lesson->title : '—' }}</td>
                                <td>
                                    @if (! $document->allow_view)
                                        <span class="small text-muted">—</span>
                                    @elseif ($progressByDocument[$document->id]?->completed_at ?? false)
                                        <x-badge tone="success">Đã xem</x-badge>
                                    @else
                                        <x-badge tone="secondary">Chưa xem</x-badge>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($document->allow_view)
                                        <x-button :href="route('courses.documents.show', [$course, $document])" variant="outline-primary" class="btn-sm" target="_blank"><i class="bi bi-eye me-1" aria-hidden="true"></i>Xem</x-button>
                                    @endif
                                    @if ($document->allow_download)
                                        <x-button :href="route('courses.documents.download', [$course, $document])" variant="outline-secondary" class="btn-sm"><i class="bi bi-download me-1" aria-hidden="true"></i>Tải về</x-button>
                                    @endif
                                    @unless ($document->allow_view || $document->allow_download)
                                        <span class="small text-muted">Không có quyền truy cập</span>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-empty-state title="Chưa có bài tập" message="Giảng viên chưa có bài tập nào được duyệt cho khóa học này." icon="file-earmark" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    <!-- GIÁO TRÌNH NỘI DUNG KHÓA HỌC (TS3-02, TS3-04) -->
    <x-card class="p-4">
        <h2 class="h4 mb-3">Giáo trình & Nội dung học tập</h2>
        <p class="text-muted small mb-4">Danh sách các chương, bài học và học liệu đi kèm trong khóa học.</p>

        @if($course->chapters->isEmpty())
            <x-empty-state
                icon="diagram-3"
                title="Khóa học đang cập nhật nội dung"
                message="Giảng viên chưa đăng tải chương bài học cho khóa học này."
            />
        @else
            <div class="accordion" id="curriculumOverviewAccordion">
                @foreach($course->chapters as $cIndex => $chapter)
                    <div class="accordion-item border rounded mb-3 overflow-hidden shadow-sm">
                        <h3 class="accordion-header" id="headingOverview{{ $chapter->id }}">
                            <button class="accordion-button bg-light py-3 text-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOverview{{ $chapter->id }}" aria-expanded="true">
                                <span class="badge bg-secondary me-2">Chương {{ $cIndex + 1 }}</span>
                                {{ $chapter->title }}
                                <span class="badge bg-light text-muted border ms-3 small fw-normal">
                                    {{ $chapter->lessons->count() }} bài học
                                </span>
                            </button>
                        </h3>
                        <div id="collapseOverview{{ $chapter->id }}" class="accordion-collapse collapse show">
                            <div class="accordion-body p-0">
                                @if($chapter->lessons->isEmpty())
                                    <div class="p-3 text-muted text-center small">Chưa có bài học trong chương này.</div>
                                @else
                                    <ul class="list-group list-group-flush">
                                        @foreach($chapter->lessons as $lIndex => $lesson)
                                            @php
                                                $isLessonDone = $isEnrolled ? $lesson->isCompletedBy(auth()->user()) : false;
                                            @endphp
                                            <li class="list-group-item p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                                <div>
                                                    <div class="fw-semibold d-flex align-items-center gap-2">
                                                        @if($isEnrolled)
                                                            <i class="bi {{ $isLessonDone ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' }}"></i>
                                                        @endif
                                                        <span class="text-muted small">Bài {{ $cIndex + 1 }}.{{ $lIndex + 1 }}:</span>
                                                        {{ $lesson->title }}
                                                    </div>
                                                    <div class="small text-muted mt-1">
                                                        <i class="bi bi-paperclip me-1"></i>{{ $lesson->documents->count() }} học liệu
                                                        @foreach($lesson->documents as $doc)
                                                            <span class="badge bg-light text-muted border ms-1">
                                                                <i class="bi {{ $doc->typeIcon() }}"></i> {{ $doc->typeLabel() }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                @if($isEnrolled)
                                                    <a href="{{ route('student.courses.learn', [$course, $lesson->id]) }}" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-play me-1"></i>Học bài này
                                                    </a>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    @if ($isEnrolled)
    <!-- DANH SÁCH TÀI LIỆU KHÓA HỌC DÀNH CHO HỌC VIÊN -->
    @php
        $approvedDocuments = $course->documents()->with('lesson')->approved()->latest()->get();
    @endphp

    <x-card class="p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 mb-1"><i class="bi bi-folder2-open text-primary me-2"></i>Tài liệu &amp; Học liệu khóa học ({{ $approvedDocuments->count() }})</h2>
                <p class="text-muted small mb-0">Học liệu chính thức đã được kiểm duyệt: bạn có thể mở xem trực tuyến hoặc tải tệp gốc về máy.</p>
            </div>
            <span class="badge bg-light text-dark border">
                {{ $approvedDocuments->count() }} tài liệu
            </span>
        </div>

        @if($approvedDocuments->isEmpty())
            <x-empty-state title="Chưa có học liệu" message="Khóa học này hiện chưa có tài liệu nào được công khai." />
        @else
            <div class="list-group list-group-flush border rounded-3 overflow-hidden">
                @foreach($approvedDocuments as $doc)
                    <div class="list-group-item p-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi {{ $doc->typeIcon() }} fs-2"></i>
                            <div>
                                <div class="fw-semibold text-dark">{{ $doc->title }}</div>
                                <div class="small text-muted">
                                    @if($doc->file_path)
                                        <span>{{ $doc->original_name }} · {{ $doc->formattedSize() }}</span>
                                    @elseif($doc->url)
                                        <span class="text-truncate d-inline-block" style="max-width: 250px;">{{ $doc->url }}</span>
                                    @endif

                                    @if($doc->lesson)
                                        <span class="badge bg-light text-dark border ms-2">
                                            <i class="bi bi-journal-bookmark me-1"></i>{{ $doc->lesson->title }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border ms-2">
                                            Tài liệu chung
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            @if($isEnrolled)
                                @if($doc->allow_view)
                                    <a href="{{ route('courses.documents.show', [$course, $doc]) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>Xem trực tuyến
                                    </a>
                                @endif
                                @if($doc->allow_download)
                                    <a href="{{ route('courses.documents.download', [$course, $doc]) }}" class="btn btn-sm btn-success">
                                        <i class="bi bi-download me-1"></i>Tải về
                                    </a>
                                @endif
                            @else
                                <span class="small text-muted fst-italic">
                                    <i class="bi bi-lock me-1"></i>Đăng ký khóa học để xem tài liệu
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
    @endif
@endsection
