@props(['type' => 'danger', 'tone' => null, 'message' => null])

@php
    $resolvedType = $tone ?? $type;
    $icon = match ($resolvedType) {
        'success' => 'check-circle-fill',
        'warning' => 'exclamation-triangle-fill',
        'info' => 'info-circle-fill',
        default => 'exclamation-circle-fill',
    };
@endphp

@if ($message || $slot->isNotEmpty())
    <div {{ $attributes->class(["alert alert-{$resolvedType} d-flex align-items-start gap-2"]) }} role="alert">
        <i class="bi bi-{{ $icon }} mt-1 flex-shrink-0" aria-hidden="true"></i>
        <div class="flex-grow-1 min-w-0">{{ $message ?? $slot }}</div>
    </div>
@endif
