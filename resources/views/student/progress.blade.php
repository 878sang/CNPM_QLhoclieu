@extends('layouts.student')

@section('content')
    <x-page-header
        eyebrow="HỌC TẬP"
        title="Tiến độ học tập"
        description="Theo dõi các khóa học bạn đang tham gia."
    />

    <form method="GET" action="{{ route('student.progress') }}" class="row g-2 mb-4">
        <div class="col-12 col-md-6">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tìm theo tên hoặc môn học">
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
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Khóa học</th>
                        <th>Môn học</th>
                        <th>Trạng thái</th>
                        <th>Tiến độ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($courses as $course)
                        <tr>
                            <td class="fw-semibold"><a href="{{ route('student.courses.show', $course) }}">{{ $course->title }}</a></td>
                            <td>{{ $course->subject?->name }}</td>
                            <td><x-badge tone="secondary">Chưa có dữ liệu tiến độ</x-badge></td>
                            <td style="min-width: 180px">
                                <div class="progress lm-progress">
                                    <div class="progress-bar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">Chưa có dữ liệu bài học</small>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state
                                    title="Chưa có tiến độ"
                                    message="Bạn chưa tham gia khóa học nào."
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
