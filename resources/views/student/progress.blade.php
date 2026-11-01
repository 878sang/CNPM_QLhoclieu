@extends('layouts.student')

@section('content')
    <x-page-header
        eyebrow="HỌC TẬP"
        title="Tiến độ học tập"
        description="Theo dõi các khóa học bạn đang tham gia."
    />

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
                            <td class="fw-semibold">{{ $course->title }}</td>
                            <td>{{ $course->subject?->name }}</td>
                            <td><x-badge tone="secondary">Chưa bắt đầu</x-badge></td>
                            <td style="min-width: 180px">
                                <div class="progress lm-progress">
                                    <div class="progress-bar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">Chưa có bài học</small>
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
