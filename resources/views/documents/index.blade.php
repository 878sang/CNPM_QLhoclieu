@extends('layouts.app')
@section('content')
<x-page-header eyebrow="KHÓA HỌC / TÀI LIỆU" :title="$course->title" description="Tải lên, chỉnh sửa tài liệu và gắn vào bài học.">
    <x-slot:actions><x-button :href="route('courses.index')" variant="outline-primary">Quay lại danh sách</x-button></x-slot:actions>
</x-page-header>
<x-card class="p-3 p-md-4 mb-4">
    <form method="POST" action="{{ route('courses.documents.store', $course) }}" enctype="multipart/form-data">
        @csrf
        <h2 class="h5 mb-3">Thêm tài liệu</h2>
        @include('documents._fields', ['document' => null])
        <x-button><i class="bi bi-upload me-2" aria-hidden="true"></i>Tải lên</x-button>
    </form>
</x-card>
<x-card class="p-3 p-md-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Tài liệu</th><th class="d-none d-md-table-cell">Bài học</th><th>Quyền</th><th class="text-end">Thao tác</th></tr></thead>
            <tbody>
                @forelse($documents as $document)
                    <tr>
                        <td><div class="fw-semibold">{{ $document->title }}</div><div class="small text-muted">{{ $document->original_name }} · {{ number_format($document->size / 1024, 0, ',', '.') }} KB</div></td>
                        <td class="d-none d-md-table-cell">{{ $document->lesson ? $document->lesson->chapter->title.' / '.$document->lesson->title : '—' }}</td>
                        <td>
                            <x-badge :tone="$document->allow_view ? 'success' : 'secondary'">Xem</x-badge>
                            <x-badge :tone="$document->allow_download ? 'success' : 'secondary'">Tải về</x-badge>
                        </td>
                        <td class="text-end text-nowrap">
                            <x-button :href="route('courses.documents.show', [$course, $document])" variant="outline-primary" class="btn-sm" target="_blank">Xem</x-button>
                            <x-button :href="route('courses.documents.edit', [$course, $document])" variant="outline-secondary" class="btn-sm">Sửa</x-button>
                            <form method="POST" action="{{ route('courses.documents.destroy', [$course, $document]) }}" class="d-inline" onsubmit="return confirm('Xóa tài liệu này?')">
                                @csrf
                                @method('DELETE')
                                <x-button variant="outline-danger" class="btn-sm">Xóa</x-button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state title="Chưa có tài liệu" message="Hãy tải lên tài liệu đầu tiên cho khóa học." icon="file-earmark" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $documents->links('pagination::bootstrap-5') }}</div>
</x-card>
@endsection
