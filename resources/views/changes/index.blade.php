@extends('layouts.ursb')
@section('title', 'Change requests')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Change requests</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Controlled changes to the canonical model (PRD §9.3.3).</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Target</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Raised by</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($changes as $cr)
                <tr>
                    <td><code class="text-[12px] text-pine">{{ $cr->ref }}</code></td>
                    <td><code class="text-[12px] text-gray-600">{{ $cr->target->ref }}</code></td>
                    <td class="text-gray-900">{{ $cr->title }}</td>
                    <td>
                        <span class="badge {{ $cr->status === 'applied' ? 'badge-pine' : ($cr->status === 'rejected' ? 'badge-danger' : ($cr->status === 'approved' ? 'badge-teal' : 'badge-flag')) }}">{{ $cr->status }}</span>
                    </td>
                    <td class="text-gray-500">{{ $cr->raisedBy?->name ?? '—' }}</td>
                    <td class="text-right">
                        <a class="btn-secondary !py-1.5 !px-3" href="{{ route('changes.show', $cr) }}">Open</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"><p class="text-sm text-gray-400 italic">No change requests.</p></td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
