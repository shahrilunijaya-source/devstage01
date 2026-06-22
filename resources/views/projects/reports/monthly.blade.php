@extends('layouts.app')
@section('page-title', 'Monthly Reports')

@section('topbar-actions')
    @can('generateMonthly', [$project])
    <button onclick="document.getElementById('generate-modal').classList.remove('hidden')" class="btn-secondary">
        + Generate / Backfill
    </button>
    @endcan
@endsection

@section('content')
<x-card>
    <x-card-header title="Monthly Reports"
        sub="Auto-stamped on the 1st for the previous month · distributed to directors, PM & PEs"/>
    <table class="data-table">
        <thead><tr>
            <th>Month</th><th>Generated</th><th>Stamped At</th><th>Distributed</th><th class="text-right">Report</th>
        </tr></thead>
        <tbody>
        @forelse($reports as $r)
        <tr>
            <td class="font-semibold">{{ $r->reporting_month->format('F Y') }}</td>
            <td class="text-[12px] text-gray-500">
                {{ $r->generatedBy ? $r->generatedBy->name : 'System (auto)' }}
            </td>
            <td class="text-[12px] text-gray-400">{{ $r->finalised_at?->format('d/m/Y H:i') ?? '—' }}</td>
            <td class="text-[12px]">
                @php $lastSend = $r->deliveries->sortByDesc('sent_at')->first(); @endphp
                @if($lastSend && $lastSend->status === 'sent')
                <span class="text-green-600 font-medium text-[11px]">● {{ $lastSend->sent_at->format('d/m/Y') }}</span>
                @elseif($lastSend && $lastSend->status === 'failed')
                <span class="text-red-500 font-medium text-[11px]">● Failed</span>
                @else
                <span class="text-gray-300 text-[11px]">—</span>
                @endif
            </td>
            <td class="text-right whitespace-nowrap">
                <a href="{{ route('projects.reports.monthly.show', [$project, $r]) }}"
                   class="text-teal text-[12px] font-medium hover:text-teal-700">Open</a>
                <span class="text-gray-200 mx-1">|</span>
                <a href="{{ route('projects.reports.monthly.pdf', [$project, $r]) }}"
                   class="text-gray-500 text-[12px] font-medium hover:text-gray-800">PDF</a>
            </td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center py-10 text-gray-300">No reports yet. The first stamp lands on the 1st of next month.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="p-4">{{ $reports->links() }}</div>
</x-card>

{{-- Generate / backfill modal — produces the same immutable stamp the scheduler does --}}
<div id="generate-modal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-6 w-80 shadow-xl">
        <h3 class="text-[15px] font-semibold text-gray-900 mb-1">Generate / Backfill Report</h3>
        <p class="text-[12px] text-gray-500 mb-4">Stamps a month now and emails it to the team. Reports are immutable.</p>
        <form method="POST" action="{{ route('projects.reports.monthly.generate', $project) }}">
            @csrf
            <label class="form-label">Select Month</label>
            <input type="month" name="reporting_month" value="{{ date('Y-m', strtotime('-1 month')) }}"
                class="form-input mb-4" required>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex-1 justify-center">Stamp Now</button>
                <button type="button" onclick="document.getElementById('generate-modal').classList.add('hidden')"
                    class="btn-secondary flex-1 justify-center">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection
