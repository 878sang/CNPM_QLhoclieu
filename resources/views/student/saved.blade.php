@extends('layouts.student')

@section('content')
<x-page-header
    eyebrow="THƯ VIỆN CÁ NHÂN"
    title="Bài giảng & Học liệu đã lưu"
    description="Truy cập nhanh các bài học và tài liệu quan trọng bạn đã đánh dấu để ôn tập trước kỳ thi."
/>

<x-card class="p-3 p-md-4 mb-4">
    <form method="GET" action="{{ route('student.saved') }}" class="row g-2 align-items-end">
        <div class="col-12 col-md-5">
            <label for="saved_q" class="form-label small fw-semibold text-muted mb-1">Tìm kiếm</label>
            <input type="search" name="q" id="saved_q" value="{{ $term }}" class="form-control" placeholder="Tìm theo tên bài học, tài liệu hoặc khóa học...">
        </div>
        <div class="col-6 col-md-3">
            <label for="saved_course" class="form-label small fw-semibold text-muted mb-1">Khóa học</label>
            <select name="course_id" id="saved_course" class="form-select">
                <option value="">Tất cả khóa học</option>
                @foreach($savedCourses as $sc)
                    <option value="{{ $sc->id }}" @selected($selectedCourseId === $sc->id)>{{ $sc->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="saved_type" class="form-label small fw-semibold text-muted mb-1">Phân loại</label>
            <select name="type" id="saved_type" class="form-select">
                <option value="all" @selected($selectedType === 'all')>Tất cả</option>
                <option value="lesson" @selected($selectedType === 'lesson')>Bài giảng</option>
                <option value="document" @selected($selectedType === 'document')>Tài liệu tệp</option>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-funnel me-1"></i>Lọc
            </button>
            <a href="{{ route('student.saved') }}" class="btn btn-outline-secondary">Xóa</a>
        </div>
    </form>
</x-card>

<div class="d-flex flex-column gap-3">
    @forelse($savedItems as $item)
        @if($item->isDocumentItem() && $item->document && $item->course)
            @php $doc = $item->document; @endphp
            <div class="card border rounded-3 p-3 p-md-4 bg-white shadow-2xs">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-start gap-3">
                        <span class="fs-3"><i class="bi {{ $doc->typeIcon() }}"></i></span>
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="badge bg-info-subtle text-info-emphasis border">Tài liệu đã lưu</span>
                                @if($item->course->subject)
                                    <span class="badge bg-primary-subtle text-primary">{{ $item->course->subject->name }}</span>
                                @endif
                                <span class="small text-muted">Lưu lúc {{ $item->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            <h3 class="h6 fw-bold text-dark mb-1">{{ $doc->title }}</h3>
                            <div class="small text-muted">
                                Khóa học: <strong>{{ $item->course->title }}</strong>
                                @if($doc->lesson)
                                    · Bài giảng: {{ $doc->lesson->title }}
                                @endif
                                @if($doc->file_path)
                                    · {{ $doc->formattedSize() }}
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @if($doc->lesson_id)
                            <a href="{{ route('student.courses.learn', [$item->course, $doc->lesson_id]) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-journal-text me-1"></i>Vào bài học
                            </a>
                        @endif
                        @if($doc->allow_view)
                            <a href="{{ route('courses.documents.show', [$item->course, $doc]) }}" target="_blank" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye me-1"></i>Xem học liệu
                            </a>
                        @endif
                        @if($doc->allow_download)
                            <a href="{{ route('courses.documents.download', [$item->course, $doc]) }}" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-download me-1"></i>Tải về
                            </a>
                        @endif
                        <form method="POST" action="{{ route('student.saved.destroy', $item) }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Bỏ lưu">
                                <i class="bi bi-bookmark-x me-1"></i>Bỏ lưu
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @elseif($item->lesson && $item->course)
            @php $lesson = $item->lesson; @endphp
            <div class="card border rounded-3 p-3 p-md-4 bg-white shadow-2xs">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-start gap-3">
                        <span class="fs-3 text-primary"><i class="bi bi-journal-bookmark-fill"></i></span>
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="badge bg-primary-subtle text-primary border">Bài giảng đã lưu</span>
                                @if($item->course->subject)
                                    <span class="badge bg-secondary-subtle text-secondary">{{ $item->course->subject->name }}</span>
                                @endif
                                <span class="small text-muted">Lưu lúc {{ $item->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            <h3 class="h6 fw-bold text-dark mb-1">{{ $lesson->title }}</h3>
                            <div class="small text-muted">
                                Khóa học: <strong>{{ $item->course->title }}</strong>
                                @if($lesson->chapter)
                                    · {{ $lesson->chapter->title }}
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('student.courses.learn', [$item->course, $lesson->id]) }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-play-circle-fill me-1"></i>Mở bài giảng
                        </a>
                        <form method="POST" action="{{ route('student.saved.destroy', $item) }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Bỏ lưu">
                                <i class="bi bi-bookmark-x me-1"></i>Bỏ lưu
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @empty
        <x-empty-state
            icon="bookmark-star"
            title="Chưa có bài học hoặc tài liệu nào được lưu"
            message="Khi đang học bài giảng hoặc xem tài liệu, hãy bấm nút 'Lưu bài học' (biểu tượng đánh dấu trang) để lưu vào thư viện ôn tập cá nhân."
        />
    @endforelse
</div>

<div class="mt-4">{{ $savedItems->links('pagination::bootstrap-5') }}</div>
@endsection
