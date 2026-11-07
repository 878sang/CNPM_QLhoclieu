@extends('layouts.app')
@section('content')
<x-page-header eyebrow="KHÓA HỌC / TÀI LIỆU" title="Chỉnh sửa tài liệu" :description="'Tệp hiện tại: '.$document->original_name">
    <x-slot:actions><x-button :href="route('courses.documents.index', $course)" variant="outline-primary">Quay lại danh sách</x-button></x-slot:actions>
</x-page-header>
<div class="row"><div class="col-xl-8"><x-card class="p-4 p-md-5">
    <form method="POST" action="{{ route('courses.documents.update', [$course, $document]) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('documents._fields')
        <x-button>Lưu thay đổi</x-button>
    </form>
</x-card></div></div>
@endsection
