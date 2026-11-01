@props(['type' => 'danger', 'message' => null])

@if ($message || $slot->isNotEmpty())
    <div class="alert alert-{{ $type }}" role="alert">
        <i
            class="bi bi-{{ $type === 'success' ? 'check-circle' : 'exclamation-circle' }} me-2"
            aria-hidden="true"
        ></i>
        {{ $message ?? $slot }}
    </div>
@endif
