@extends('layouts.student')

@section('content')
    <x-page-header
        eyebrow="HỌC TẬP / CHI TIẾT KHÓA HỌC"
        :title="$course->title"
        description="Thông tin khóa học bạn đang tham gia."
    >
        <x-slot:actions>
            <x-button :href="route('student.courses')" variant="outline-primary">Quay lại khóa học</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-8">
            <x-card class="p-4 h-100">
                <span class="student-course-cover mb-4">
                    <i class="bi bi-journal-bookmark"></i>
                </span>
                <x-badge tone="primary">{{ $course->subject?->name }}</x-badge>
                <h1 class="h3 mt-3">{{ $course->title }}</h1>
                <p class="text-muted">{{ $course->description ?: 'Khóa học chưa có mô tả.' }}</p>
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
                    <dd class="col-7">Chưa có dữ liệu</dd>
                </dl>
                @if ($isEnrolled)
                    <form method="POST" action="{{ route('student.courses.leave', $course) }}" class="mt-4" onsubmit="return confirm('Bạn có chắc muốn rời khỏi khóa học này?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger w-100">Rời khóa học</button>
                    </form>
                @else
                    <div class="alert alert-info mt-4 mb-0">Bạn chưa tham gia khóa học này.</div>
                @endif
            </x-card>
        </div>
    </div>
    @if ($isEnrolled)
        <x-card class="p-4 mt-4">
            <h2 class="h5 mb-3">Bài tập &amp; tài liệu</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Tên bài tập</th><th class="d-none d-md-table-cell">Bài học</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody>
                        @forelse ($documents as $document)
                            <tr>
                                <td><div class="fw-semibold">{{ $document->title }}</div><div class="small text-muted">{{ $document->original_name }} · {{ number_format($document->size / 1024, 0, ',', '.') }} KB</div></td>
                                <td class="d-none d-md-table-cell">{{ $document->lesson ? $document->lesson->chapter->title.' / '.$document->lesson->title : '—' }}</td>
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
                            <tr><td colspan="3"><x-empty-state title="Chưa có bài tập" message="Giảng viên chưa có bài tập nào được duyệt cho khóa học này." icon="file-earmark" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
@endsection
