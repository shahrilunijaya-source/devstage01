@props([
    'value' => 0,            // 0–100
    'color' => 'teal',       // teal | pine | flag | danger
    'height' => 'h-1.5',
])
@php
    $pct = max(0, min(100, (float) $value));
    $fill = [
        'teal' => 'linear-gradient(90deg,#00c6b6,#00a899)',
        'pine' => 'linear-gradient(90deg,#0a534e,#003d3a)',
        'flag' => 'linear-gradient(90deg,#fbbf24,#f59e0b)',
        'danger' => 'linear-gradient(90deg,#f87171,#dc2626)',
    ][$color] ?? 'linear-gradient(90deg,#00c6b6,#00a899)';
@endphp
<div {{ $attributes->merge(['class' => "w-full $height rounded-full overflow-hidden bg-gray-100"]) }}>
    <div class="h-full rounded-full transition-[width] duration-700 ease-out"
         style="width: {{ $pct }}%; background: {{ $fill }};"></div>
</div>
