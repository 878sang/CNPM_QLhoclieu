@props(['title', 'message', 'icon' => 'journal-bookmark'])

<div class="lm-empty lm-card">
    <div class="lm-empty-art">
        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    </div>
    <h2 class="h4">{{ $title }}</h2>
    <p class="lm-text-muted mb-0">{{ $message }}</p>
    {{ $slot }}
</div>
