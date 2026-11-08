@extends('layouts.student')

@section('content')
    <x-page-header
        eyebrow="HỌC TẬP"
        title="Tiến độ học tập"
        description="Theo dõi chi tiết mức độ hoàn thành bài học và khóa học bạn đang tham gia."
    />

    <form method="GET" action="{{ route('student.progress') }}" class="row g-2 mb-4">
        <div class="col-12 col-md-6">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tìm theo tên khóa học hoặc môn học">
        </div>
        <div class="col-12 col-md-4">
            <select name="subject_id" class="form-select">
                <option value="">Tất cả môn học</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Lọc</button>
            <a href="{{ route('student.progress') }}" class="btn btn-outline-secondary">Xóa</a>
        </div>
    </form>

    <x-card class="p-3 p-md-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Khóa học</th>
                        <th>Môn học</th>
                        <th>Trạng thái</th>
                        <th style="min-width: 220px;">Tiến độ hoàn thành</th>
                        <th class="text-end">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($courses as $course)
                        @php
                            $user = auth()->user();
                            $percent = $course->progressPercentageFor($user);
                            $totalLessons = $course->chapters->sum(fn ($c) => $c->lessons->count());
                            $completedLessons = $course->progresses->where('is_completed', true)->count();
                            $lastLesson = $course->lastStudiedLessonFor($user);
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded overflow-hidden border flex-shrink-0" style="width: 70px; height: 42px;">
                                        <img src="{{ $course->cover_url }}" alt="{{ $course->title }}" class="w-100 h-100 object-fit-cover">
                                    </div>
                                    <div>
                                        <div class="fw-semibold">
                                            <a href="{{ route('student.courses.show', $course) }}" class="text-dark text-decoration-none hover-primary">
                                                {{ $course->title }}
                                            </a>
                                        </div>
                                        <div class="small text-muted">{{ $course->teacher?->name ?: 'Giảng viên' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <x-badge tone="primary">{{ $course->subject?->name }}</x-badge>
                            </td>
                            <td>
                                <x-badge :tone="$course->progressStatusTone($user)">
                                    {{ $course->progressStatusLabel($user) }}
                                </x-badge>
                            </td>
                            <td>
                                <div class="d-flex justify-content-between align-items-center small mb-1">
                                    <span class="text-muted">{{ $completedLessons }}/{{ $totalLessons }} bài học</span>
                                    <span class="fw-bold text-primary">{{ $percent }}%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percent }}%" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('student.courses.learn', [$course, $lastLesson?->id]) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-play-circle me-1"></i>{{ $percent > 0 ? 'Tiếp tục học' : 'Bắt đầu' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-empty-state
                                    title="Chưa có dữ liệu tiến độ"
                                    message="Bạn chưa tham gia khóa học nào. Hãy đăng ký khóa học để bắt đầu theo dõi tiến độ."
                                    icon="bar-chart"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
