@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : (auth()->user()->hasRole('teacher') ? 'layouts.teacher' : 'layouts.student'))

@section('content')
    <x-page-header
        eyebrow="TỔNG QUAN"
        title="Chào {{ auth()->user()->name }}"
        description="Tổng quan được cập nhật từ dữ liệu thực tế trong hệ thống."
    >
        <x-slot:actions>
            <x-button :href="route($workspaceRoute)" variant="outline-primary">
                <i class="bi bi-arrow-right me-2" aria-hidden="true"></i>
                Mở không gian của tôi
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-md-4 fade-up">
            <x-card class="p-4 h-100">
                <span class="lm-icon"><i class="bi bi-journal-text" aria-hidden="true"></i></span>
                <div class="font-mono display-6 mt-4">{{ $courseCount }}</div>
                <div class="lm-text-muted">{{ $courseLabel }}</div>
            </x-card>
        </div>

        <div class="col-md-4 fade-up stagger-2">
            <x-card class="p-4 h-100">
                <span class="lm-icon"><i class="bi bi-book" aria-hidden="true"></i></span>
                <div class="font-mono display-6 mt-4">{{ $subjectCount }}</div>
                <div class="lm-text-muted">Môn học trên hệ thống</div>
            </x-card>
        </div>

        <div class="col-md-4 fade-up stagger-3">
            <x-card class="p-4 h-100">
                <span class="lm-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                <div class="font-mono display-6 mt-4">{{ $courseList->count() }}</div>
                <div class="lm-text-muted">Khóa học đang hiển thị</div>
            </x-card>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <x-card class="p-4">
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                    <div>
                        <h2 class="h4 mb-1">{{ $courseHeading }}</h2>
                        <p class="lm-text-muted small mb-0">Danh sách được lấy trực tiếp từ hệ thống.</p>
                    </div>
                    <span class="font-mono small text-muted">{{ $courseList->count() }} khóa</span>
                </div>

                <div class="d-flex flex-column gap-3">
                    @forelse($courseList as $course)
                        <div class="card border rounded-3 overflow-hidden shadow-2xs hover-shadow transition-all bg-white p-2">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded overflow-hidden border flex-shrink-0" style="width: 120px; height: 75px;">
                                    <img src="{{ $course->cover_url }}" alt="{{ $course->title }}" class="w-100 h-100 object-fit-cover">
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <x-badge tone="primary">{{ $course->subject?->name }}</x-badge>
                                    </div>
                                    <h3 class="h6 mb-1 fw-bold">
                                        <a href="{{ auth()->user()->hasRole('student') ? route('student.courses.show', $course) : route('courses.show', $course) }}" class="text-dark text-decoration-none hover-primary">
                                            {{ $course->title }}
                                        </a>
                                    </h3>
                                    <p class="small text-muted mb-0 text-truncate" style="max-width: 400px;">
                                        {{ $course->description ?: 'Khóa học ôn luyện trọng tâm kiến thức.' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <x-empty-state
                                title="Chưa có khóa học"
                                message="Khi có khóa học phù hợp, dữ liệu sẽ xuất hiện ở đây."
                                icon="journal-bookmark"
                            />
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>

        <div class="col-xl-4">
            <x-card class="p-4 h-100" style="border-top: 3px solid var(--accent)">
                <div class="font-mono text-accent small">GỢI Ý</div>
                <h2 class="h3 mt-3">Học theo từng bước.</h2>
                <p class="lm-text-muted">
                    Chọn một khóa học, đọc học liệu và quay lại thường xuyên để duy trì tiến độ ôn thi.
                </p>
                <a href="{{ auth()->user()->hasRole('student') ? route('student.search') : route('courses.index') }}" class="btn btn-outline-primary mt-3">
                    Xem khóa học
                </a>
            </x-card>
        </div>
    </div>
@endsection
