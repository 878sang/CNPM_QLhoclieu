@props(['class' => ''])
<section {{ $attributes->merge(['class' => "lm-card $class"]) }}>{{ $slot }}</section>
