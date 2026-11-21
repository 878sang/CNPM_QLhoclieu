<!doctype html>
<html lang="vi">
<head><meta charset="utf-8"><title>{{ $notificationTitle }}</title></head>
<body style="font-family:Arial,sans-serif;color:#172554;line-height:1.6;padding:24px">
    <h1 style="font-size:24px">{{ $notificationTitle }}</h1>
    <p style="white-space:pre-line">{{ $notificationMessage }}</p>
    @if($actionUrl && in_array(parse_url($actionUrl, PHP_URL_SCHEME), ['http', 'https'], true))
        <p><a href="{{ $actionUrl }}">Xem chi tiết trong hệ thống học liệu</a></p>
    @endif
    <p>{{ config('app.name') }}</p>
</body>
</html>
