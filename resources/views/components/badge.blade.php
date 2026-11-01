@props(['tone' => 'brand'])
<span {{ $attributes->merge(['class' => "badge text-bg-$tone"]) }}>{{ $slot }}</span>
