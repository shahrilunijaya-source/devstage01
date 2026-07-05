@extends('layouts.ursb')
@section('title', $project->name.' — team')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 tracking-tight">Project team</h1>
        <p class="text-[13px] text-gray-500 mt-0.5">Everyone holding an active access binding on this project (PRD §6).</p>
    </div>

    @if ($bindings->isEmpty())
        <div class="card card-pad"><p class="text-sm text-gray-400 italic">No active bindings on this project yet.</p></div>
    @else
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead><tr><th>Member</th><th class="w-[180px]">Role</th><th class="w-[120px]">Scope</th><th class="w-[160px]">Expires</th></tr></thead>
                <tbody>
                @foreach ($bindings as $b)
                    <tr>
                        <td class="text-gray-900">{{ $b->user?->name ?? '#'.$b->user_id }}
                            @if ($b->user?->email)<div class="text-[12px] text-gray-400">{{ $b->user->email }}</div>@endif
                        </td>
                        <td><span class="badge badge-pine">{{ $b->role?->name ?? $b->role?->key ?? '—' }}</span></td>
                        <td class="text-gray-500 text-[13px]">{{ ucfirst($b->scope_type) }}</td>
                        <td class="text-gray-500 text-[13px]">{{ $b->ends_at ? $b->ends_at->toDayDateTimeString() : 'Permanent' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
