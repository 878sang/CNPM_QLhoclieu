@props(['title', 'description' => null, 'eyebrow' => null])
<header class="lm-page-header fade-up">
    @if ($eyebrow)
        <div class="font-mono text-accent small text-uppercase mb-2">{{ $eyebrow }}</div>
    @endif

    <h1>{{ $title }}</h1>
    <div class="lm-underline"></div>

    @if ($description)
        <p>{{ $description }}</p>
    @endif

    @if (isset($actions))
        <div class="mt-3">{{ $actions }}</div>
    @endif
</header>
