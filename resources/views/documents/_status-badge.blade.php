@php
    $map = [
        'pending' => ['warning', 'Chờ duyệt'],
        'approved' => ['success', 'Đã duyệt'],
        'rejected' => ['danger', 'Bị từ chối'],
    ];
    [$tone, $label] = $map[$document->approval_status] ?? ['secondary', $document->approval_status];
@endphp
<x-badge :tone="$tone">{{ $label }}</x-badge>
@if ($document->conversion_status === 'converted')
    <x-badge tone="info"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Đã chuyển PDF</x-badge>
@elseif ($document->conversion_status === 'failed')
    <x-badge tone="secondary" title="Không chuyển được sang PDF, học viên sẽ xem tệp gốc">Chuyển PDF lỗi</x-badge>
@endif
