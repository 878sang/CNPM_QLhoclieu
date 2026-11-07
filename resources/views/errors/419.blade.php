@extends('layouts.guest')

@section('content')
    <div class="min-vh-100 d-flex align-items-center justify-content-center">
        <div class="text-center">
            <div class="font-mono text-accent display-6">419</div>
            <h1 class="display-5">Phiên làm việc đã hết hạn.</h1>
            <p class="lm-text-muted">Hãy tải lại trang và thử lại thao tác.</p>
            <a href="{{ url()->previous() }}" class="btn btn-primary mt-3">Quay lại</a>
        </div>
    </div>
@endsection
