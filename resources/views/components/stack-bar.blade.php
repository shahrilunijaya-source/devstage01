@props([
    'segments' => [],   // [ ['label'=>'Traced','count'=>12,'fill'=>'#003d3a'], ... ]
    'height' => 'h-2.5',
])
@php
    $total = collect($segments)->sum('count') ?: 1;
@endphp
<div>
    <div class="flex w-full {{ $height }} rounded-full overflow-hidden bg-gray-100">
        @foreach ($segments as $seg)
            @php $w = round(($seg['count'] / $total) * 100, 2); @endphp
            @if ($seg['count'] > 0)
                <div class="h-full transition-[width] duration-700 ease-out first:rounded-l-full last:rounded-r-full"
                     style="width: {{ $w }}%; background: {{ $seg['fill'] }};"
                     title="{{ $seg['label'] }}: {{ $seg['count'] }}"></div>
            @endif
        @endforeach
    </div>
    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2.5">
        @foreach ($segments as $seg)
            <div class="flex items-center gap-1.5 text-[11px] text-gray-500">
                <span class="w-2 h-2 rounded-full" style="background: {{ $seg['fill'] }};"></span>
                {{ $seg['label'] }} <span class="font-semibold text-gray-700 tabular-nums">{{ $seg['count'] }}</span>
            </div>
        @endforeach
    </div>
</div>
