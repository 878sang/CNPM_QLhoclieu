@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')

@section('content')
<x-page-header eyebrow="KHÓA HỌC / HỌC LIỆU" :title="$course->title" description="Tải lên, quản lý tài liệu, bài giảng (PDF, Word, PPT, video, link) và phân quyền học viên.">
    <x-slot:actions>
        <x-button :href="route('courses.show', $course)" variant="outline-primary">
            <i class="bi bi-diagram-3 me-1"></i>Xem cây chương bài
        </x-button>
        <x-button :href="route('courses.index')" variant="outline-secondary">Quay lại danh sách</x-button>
    </x-slot:actions>
</x-page-header>

@if (session('status'))
    <x-alert tone="success" class="mb-4">{{ session('status') }}</x-alert>
@endif

<!-- DANH SÁCH HỌC LIỆU VÀ BỘ LỌC (TS2-07) -->
<x-card class="p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <h2 class="h5 mb-0">Danh sách học liệu trong khóa học ({{ $documents->total() }})</h2>

        <!-- Bộ lọc -->
        <form method="GET" action="{{ route('courses.documents.index', $course) }}" class="d-flex flex-wrap gap-2">
            <select name="type" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="">-- Tất cả loại --</option>
                <option value="file" @selected(request('type') === 'file')>Tệp tài liệu</option>
                <option value="video" @selected(request('type') === 'video')>Video bài giảng</option>
                <option value="link" @selected(request('type') === 'link')>Liên kết ngoài</option>
            </select>

            <select name="lesson_id" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="">-- Tất cả bài học --</option>
                @foreach($chapters as $chapter)
                    <optgroup label="{{ $chapter->title }}">
                        @foreach($chapter->lessons as $lesson)
                            <option value="{{ $lesson->id }}" @selected(request('lesson_id') == $lesson->id)>{{ $lesson->title }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>

            @if(request()->hasAny(['type', 'lesson_id']))
                <a href="{{ route('courses.documents.index', $course) }}" class="btn btn-sm btn-outline-secondary">Đặt lại</a>
            @endif
        </form>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0 table-hover">
            <thead class="table-light">
                <tr>
                    <th>Học liệu</th>
                    <th>Loại</th>
                    <th class="d-none d-md-table-cell">Gắn vào bài học</th>
                    <th>Quyền</th>
                    <th class="d-none d-lg-table-cell text-center">Thống kê</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $document)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi {{ $document->typeIcon() }} fs-4"></i>
                                <div>
                                    <div class="fw-semibold text-dark">{{ $document->title }}</div>
                                    <div class="small text-muted">
                                        @if($document->file_path)
                                            {{ $document->original_name }} · {{ $document->formattedSize() }}
                                        @elseif($document->url)
                                            <span class="text-truncate d-inline-block" style="max-width: 250px;">{{ $document->url }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                {{ $document->typeLabel() }}
                            </span>
                        </td>
                        <td class="d-none d-md-table-cell">
                            @if($document->lesson)
                                <div class="small fw-medium">{{ $document->lesson->chapter?->title }}</div>
                                <div class="text-muted small">↳ {{ $document->lesson->title }}</div>
                            @else
                                <span class="text-muted">— (Chung)</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <x-badge :tone="$document->allow_view ? 'success' : 'secondary'">
                                    <i class="bi {{ $document->allow_view ? 'bi-check' : 'bi-x' }}"></i>Xem trực tuyến
                                </x-badge>
                                <x-badge :tone="$document->allow_download ? 'primary' : 'secondary'">
                                    <i class="bi {{ $document->allow_download ? 'bi-check' : 'bi-x' }}"></i>Tải về
                                </x-badge>
                            </div>
                        </td>
                        <td class="d-none d-lg-table-cell text-center">
                            <div class="small text-muted">
                                <div><i class="bi bi-eye me-1"></i>{{ $document->views_count ?? 0 }} xem</div>
                                <div><i class="bi bi-download me-1"></i>{{ $document->downloads_count ?? 0 }} tải</div>
                            </div>
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('courses.documents.show', [$course, $document]) }}" target="_blank" class="btn btn-outline-primary" title="Xem trước học liệu">
                                    <i class="bi bi-eye"></i> Xem
                                </a>
                                @if($document->file_path)
                                    <a href="{{ route('courses.documents.download', [$course, $document]) }}" class="btn btn-outline-success" title="Tải về máy">
                                        <i class="bi bi-download"></i>
                                    </a>
                                @endif
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editDocumentModal{{ $document->id }}" title="Sửa thông tin">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" action="{{ route('courses.documents.destroy', [$course, $document]) }}" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa học liệu này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="Xóa">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                            @include('documents._edit-modal', ['document' => $document])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-empty-state
                                title="Chưa có học liệu nào"
                                message="Khóa học này chưa có tài liệu hay bài giảng nào. Hãy dùng form ở trên để thêm học liệu đầu tiên."
                                icon="file-earmark"
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $documents->links('pagination::bootstrap-5') }}</div>
</x-card>
@endsection
