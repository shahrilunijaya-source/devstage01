@props(['band'])
@php
    $styles = [
        'light'      => 'bg-gray-100 text-gray-700',
        'moderate'   => 'bg-green-100 text-green-800',
        'heavy'      => 'bg-amber-100 text-amber-800',
        'overloaded' => 'bg-red-100 text-red-800',
    ];
    $class = $styles[$band] ?? $styles['light'];
@endphp
<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium uppercase {{ $class }}">
    {{ $band }}
</span>
