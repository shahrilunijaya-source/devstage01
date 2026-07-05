@props(['title', 'sub' => null])
<div {{ $attributes->merge(['class' => 'px-5 py-4 border-b border-gray-100 flex items-center justify-between']) }}>
    <div>
        <div class="text-[14px] font-semibold text-gray-900">{{ $title }}</div>
        @if($sub)<div class="text-[12px] text-gray-500 mt-0.5">{{ $sub }}</div>@endif
    </div>
    @if($slot->isNotEmpty())
    <div class="flex items-center gap-2">{{ $slot }}</div>
    @endif
</div>
