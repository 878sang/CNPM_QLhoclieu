@extends('layouts.app')
@section('content')
<x-page-header eyebrow="WORKSPACE" :title="$title" description="Không gian làm việc được sắp xếp theo vai trò và quyền truy cập." />
<x-empty-state title="Bắt đầu từ đây" message="Chưa có nội dung cần xử lý. Khi dữ liệu được thêm vào, mọi thứ sẽ xuất hiện trong không gian này." icon="journal-bookmark" />
@endsection
