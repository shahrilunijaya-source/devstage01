@if($item->attachments->isNotEmpty())
<div class="mt-4 pt-4 border-t border-gray-100">
    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-2">Attachments ({{ $item->attachments->count() }})</div>
    <div class="grid grid-cols-2 gap-3">
        @foreach($item->attachments as $attachment)
            @if($attachment->isImage())
            <a href="{{ $attachment->downloadUrl('inline') }}" target="_blank"
               class="block border border-gray-200 rounded-lg overflow-hidden hover:border-teal transition-colors">
                <img src="{{ $attachment->downloadUrl('inline') }}" alt="{{ $attachment->original_name }}"
                     class="w-full h-32 object-cover bg-gray-50">
                <div class="px-2 py-1.5 text-[11px] text-gray-500 truncate">{{ $attachment->original_name }} · {{ $attachment->humanSize() }}</div>
            </a>
            @elseif($attachment->isVideo())
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <video controls preload="metadata" class="w-full h-32 bg-black object-contain">
                    <source src="{{ $attachment->downloadUrl('inline') }}" type="{{ $attachment->mime_type }}">
                </video>
                <div class="px-2 py-1.5 text-[11px] text-gray-500 truncate">{{ $attachment->original_name }} · {{ $attachment->humanSize() }}</div>
            </div>
            @else
            <a href="{{ $attachment->downloadUrl('attachment') }}"
               class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2.5 hover:border-teal transition-colors">
                <x-heroicon-o-document class="w-5 h-5 text-gray-400 shrink-0"/>
                <span class="min-w-0">
                    <span class="block text-[12px] font-medium text-gray-700 truncate">{{ $attachment->original_name }}</span>
                    <span class="block text-[11px] text-gray-400">{{ $attachment->humanSize() }}</span>
                </span>
            </a>
            @endif
        @endforeach
    </div>
</div>
@endif
