@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <div class="small text-muted mb-1">
            <a href="{{ route('courses.show', $course) }}" class="text-decoration-none text-muted">
                <i class="bi bi-arrow-left me-1"></i>{{ $course->title }}
            </a>
        </div>
        <h1 class="h4 fw-bold mb-0 text-dark">
            <i class="bi bi-journals text-primary me-2"></i>Quản lý bài tập khóa học
        </h1>
    </div>
    <a href="{{ route('courses.assignments.create', $course) }}" class="btn btn-primary fw-semibold">
        <i class="bi bi-plus-circle me-1"></i>Tạo bài tập mới
    </a>
</div>

@if(session('status'))
    <x-alert tone="success" class="mb-3">{{ session('status') }}</x-alert>
@endif

@if($assignments->isEmpty())
    <x-empty-state icon="journal-x" title="Chưa có bài tập nào"
        message="Bấm 'Tạo bài tập mới' để thêm bài tập trắc nghiệm hoặc tự luận cho khóa học này." />
@else
<div class="row g-4">
    {{-- Sidebar mục lục bài tập --}}
    <aside class="col-lg-3">
        <div class="card border rounded-3 shadow-2xs sticky-top bg-white" style="top: 1rem">
            <div class="card-header bg-white fw-bold py-3"><i class="bi bi-list-ul text-primary me-2"></i>Mục lục bài tập</div>
            <nav class="list-group list-group-flush small">
                @foreach($chapters as $chapter)
                    @php($chapterHasAssignments = $chapter->lessons->contains(fn ($lesson) => $assignments->contains('lesson_id', $lesson->id)))
                    @if($chapterHasAssignments)
                        <div class="list-group-item bg-light fw-bold text-dark py-2">
                            <i class="bi bi-folder2-open text-warning me-1"></i>{{ $chapter->title }}
                        </div>
                    @endif
                    @foreach($chapter->lessons as $lesson)
                        @php($lessonCount = $assignments->where('lesson_id', $lesson->id)->count())
                        @if($lessonCount)
                            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ps-4" href="#lesson-{{ $lesson->id }}">
                                <span class="text-truncate me-2"><i class="bi bi-journal-text me-1 text-secondary"></i>{{ $lesson->title }}</span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $lessonCount }}</span>
                            </a>
                        @endif
                    @endforeach
                @endforeach
                @if($assignments->whereNull('lesson_id')->count())
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="#course-assignments">
                        <span><i class="bi bi-collection me-2 text-primary"></i>Bài tập chung</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $assignments->whereNull('lesson_id')->count() }}</span>
                    </a>
                @endif
            </nav>
        </div>
    </aside>

    {{-- Danh sách bài tập theo chương và bài học --}}
    <div class="col-lg-9 d-flex flex-column gap-4">
        @foreach($chapters as $chapter)
            @php($chapterAssignments = $assignments->filter(fn ($assignment) => $chapter->lessons->contains('id', $assignment->lesson_id)))
            @if($chapterAssignments->isNotEmpty())
                <section>
                    <h2 class="h5 fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-folder2-open text-warning me-2"></i>{{ $chapter->title }}
                    </h2>
                    <div class="d-flex flex-column gap-3">
                        @foreach($chapter->lessons as $lesson)
                            @php($lessonAssignments = $assignments->where('lesson_id', $lesson->id))
                            @if($lessonAssignments->isNotEmpty())
                                <div id="lesson-{{ $lesson->id }}">
                                    <div class="small fw-bold text-muted mb-2 text-uppercase ps-1">
                                        <i class="bi bi-bookmark me-1 text-primary"></i>{{ $lesson->title }}
                                    </div>
                                    <div class="d-flex flex-column gap-2 mb-3">
                                        @foreach($lessonAssignments as $a)
                                            <div class="card border shadow-2xs rounded-3 overflow-hidden bg-white">
                                                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                            <span class="badge {{ $a->status === 'published' ? 'bg-success' : 'bg-warning text-dark' }} fw-semibold">
                                                                {{ $a->status === 'published' ? 'Đã xuất bản' : 'Bản nháp' }}
                                                            </span>
                                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                                {{ $a->typeLabel() }}
                                                            </span>
                                                        </div>
                                                        <h3 class="h6 fw-bold mb-1 text-dark">{{ $a->title }}</h3>
                                                        <div class="small text-muted d-flex flex-wrap gap-3">
                                                            <span><i class="bi bi-list-ol text-primary me-1"></i>{{ $a->question_count }} câu</span>
                                                            @if($a->deadline)
                                                                <span class="{{ $a->isPastDeadline() ? 'text-danger fw-semibold' : '' }}">
                                                                    <i class="bi bi-calendar-event me-1"></i>Hạn: {{ $a->deadline->format('H:i - d/m/Y') }}
                                                                </span>
                                                            @endif
                                                            <span><i class="bi bi-people text-success me-1"></i><strong>{{ $a->submissions_count }}</strong> bài nộp</span>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex gap-2 flex-shrink-0">
                                                        <a href="{{ route('courses.assignments.show', [$course, $a]) }}" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye me-1"></i>Xem
                                                        </a>
                                                        <a href="{{ route('courses.assignments.edit', [$course, $a]) }}" class="btn btn-sm btn-outline-secondary">
                                                            <i class="bi bi-pencil me-1"></i>Sửa
                                                        </a>
                                                        <form method="POST" action="{{ route('courses.assignments.destroy', [$course, $a]) }}"
                                                            onsubmit="return confirm('Xóa bài tập «{{ $a->title }}»? Toàn bộ bài nộp của học viên cũng sẽ bị xóa.')">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa bài tập">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        {{-- Bài tập chung của khóa học (không gắn bài học nào) --}}
        @php($generalAssignments = $assignments->whereNull('lesson_id'))
        @if($generalAssignments->isNotEmpty())
            <section id="course-assignments">
                <h2 class="h5 fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="bi bi-collection text-primary me-2"></i>Bài tập chung của khóa học
                </h2>
                <div class="d-flex flex-column gap-2">
                    @foreach($generalAssignments as $a)
                        <div class="card border shadow-2xs rounded-3 overflow-hidden bg-white">
                            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <span class="badge {{ $a->status === 'published' ? 'bg-success' : 'bg-warning text-dark' }} fw-semibold">
                                            {{ $a->status === 'published' ? 'Đã xuất bản' : 'Bản nháp' }}
                                        </span>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            {{ $a->typeLabel() }}
                                        </span>
                                    </div>
                                    <h3 class="h6 fw-bold mb-1 text-dark">{{ $a->title }}</h3>
                                    <div class="small text-muted d-flex flex-wrap gap-3">
                                        <span><i class="bi bi-list-ol text-primary me-1"></i>{{ $a->question_count }} câu</span>
                                        @if($a->deadline)
                                            <span class="{{ $a->isPastDeadline() ? 'text-danger fw-semibold' : '' }}">
                                                <i class="bi bi-calendar-event me-1"></i>Hạn: {{ $a->deadline->format('H:i - d/m/Y') }}
                                            </span>
                                        @endif
                                        <span><i class="bi bi-people text-success me-1"></i><strong>{{ $a->submissions_count }}</strong> bài nộp</span>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-shrink-0">
                                    <a href="{{ route('courses.assignments.show', [$course, $a]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>Xem
                                    </a>
                                    <a href="{{ route('courses.assignments.edit', [$course, $a]) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil me-1"></i>Sửa
                                    </a>
                                    <form method="POST" action="{{ route('courses.assignments.destroy', [$course, $a]) }}"
                                        onsubmit="return confirm('Xóa bài tập «{{ $a->title }}»? Toàn bộ bài nộp của học viên cũng sẽ bị xóa.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa bài tập">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
@endif
@endsection
