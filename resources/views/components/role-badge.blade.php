@props(['role'])
@php
$map = [
    'pm'     => 'bg-teal/10 text-teal-700',
    'pe'     => 'bg-pine/10 text-pine',
    'member' => 'bg-gray-100 text-gray-600',
    'admin'  => 'bg-red-100 text-red-700',
    'director' => 'bg-blue-100 text-blue-700',
    'regular' => 'bg-gray-100 text-gray-600',
    'client' => 'bg-indigo-100 text-indigo-700',
];
$cls = $map[$role] ?? 'bg-gray-100 text-gray-600';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wide $cls"]) }}>
    {{ strtoupper($role) }}
</span>
