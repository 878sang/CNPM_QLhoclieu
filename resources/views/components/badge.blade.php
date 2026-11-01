@props(['tone' => 'brand'])

@php
    $toneClasses = [
        'brand' => 'badge-brand',
        'primary' => 'text-bg-primary',
        'secondary' => 'text-bg-secondary',
        'success' => 'text-bg-success',
        'danger' => 'text-bg-danger',
        'warning' => 'text-bg-warning',
        'info' => 'text-bg-info',
        'dark' => 'text-bg-dark',
        'light' => 'text-bg-light',
    ];

    $badgeClass = $toneClasses[$tone] ?? $toneClasses['brand'];
@endphp

<span {{ $attributes->class(['badge', $badgeClass]) }}>{{ $slot }}</span>
