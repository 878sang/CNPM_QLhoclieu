@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')

@section('content')
<x-page-header
    eyebrow="KHÓA HỌC / HỌC LIỆU"
    title="Chỉnh sửa học liệu"
    :description="$document->original_name ? 'Tệp hiện tại: '.$document->original_name : ($document->url ? 'Liên kết: '.$document->url : 'Chỉnh sửa thông tin học liệu')"
>
    <x-slot:actions>
        <x-button :href="route('courses.documents.index', $course)" variant="outline-primary">Quay lại danh sách</x-button>
    </x-slot:actions>
</x-page-header>

<div class="row">
    <div class="col-xl-8">
        <x-card class="p-4 p-md-5">
            <form method="POST" action="{{ route('courses.documents.update', [$course, $document]) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('documents._fields')
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <x-button :href="route('courses.documents.index', $course)" variant="outline-secondary">Hủy</x-button>
                    <x-button>Lưu thay đổi</x-button>
                </div>
            </form>
        </x-card>
    </div>
</div>
@endsection
