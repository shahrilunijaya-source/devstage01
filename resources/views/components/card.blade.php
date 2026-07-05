@props(['class' => ''])
<div {{ $attributes->merge(['class' => "bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden $class"]) }}>
    {{ $slot }}
</div>
