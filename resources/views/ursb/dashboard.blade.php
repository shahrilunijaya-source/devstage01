@extends('layouts.ursb')
@section('title', 'Object Graph')
@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">URSB — Requirement-to-Prototype Platform</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Phase 1 Foundation · live canonical model · read-only verification view</p>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
        @foreach (['tenants' => 'Tenants', 'objects' => 'Objects', 'traces' => 'Trace edges', 'roles' => 'ACL roles', 'permissions' => 'Permissions', 'bindings' => 'Bindings'] as $key => $label)
            <div class="stat-card">
                <div class="stat-value">{{ $counts[$key] }}</div>
                <div class="stat-label">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <section class="mb-8">
        <h2 class="section-title mb-3">Portfolio hierarchy — Tenant › Project › Module › Stage</h2>
        <div class="card card-pad">
            @forelse ($tenants as $tenant)
                <div class="font-semibold text-gray-900 mt-4 first:mt-0">
                    {{ $tenant->name }}
                    <span class="text-teal font-medium text-xs ml-1">{{ $tenant->slug }} · {{ $tenant->type }}</span>
                </div>
                @foreach ($tenant->projects as $project)
                    <div class="ml-4 mt-2 pl-4 border-l-2 border-gray-200">
                        <span class="text-pine font-semibold">{{ $project->name }}</span>
                        <span class="badge badge-gray ml-1">{{ $project->code }}</span>
                        @foreach ($project->modules as $module)
                            <div class="ml-3 mt-2 text-gray-700">▸ {{ $module->name }}
                                <span class="badge badge-gray ml-1">{{ $module->code }}</span>
                                <div class="flex flex-wrap gap-1.5 mt-1.5">
                                    @foreach ($module->stages->sortBy(fn ($s) => $s->stage->order()) as $stage)
                                        <span class="badge {{ $stage->status === 'baselined' ? 'badge-teal' : 'badge-gray' }}">{{ $stage->stage->label() }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            @empty
                <p class="text-sm text-gray-400 italic">No tenants yet. Run <code class="font-mono text-pine">php artisan db:seed --class=UrsbDemoSeeder</code>.</p>
            @endforelse
        </div>
    </section>

    <section class="mb-8">
        <h2 class="section-title mb-3">Traceability chain — evidence → finding → requirement</h2>
        <div class="card card-pad">
            @if ($chain->isEmpty())
                <p class="text-sm text-gray-400 italic">No objects yet.</p>
            @else
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($chain as $obj)
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-3 max-w-[260px]">
                            <div class="font-mono font-semibold text-[13px] text-flag-500">{{ $obj->ref }}</div>
                            <div class="text-[12.5px] text-gray-800 mt-1">{{ $obj->title }}</div>
                            <div class="text-[10.5px] uppercase tracking-wide text-gray-400 mt-1">{{ $obj->status->label() }}</div>
                        </div>
                        @if (! $loop->last)<span class="text-teal text-lg">→</span>@endif
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="mb-8">
        <h2 class="section-title mb-3">Knowledge books</h2>
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead><tr><th>Kind</th><th>Version</th><th>Title</th><th>Status</th><th>Items</th></tr></thead>
                <tbody>
                @forelse ($books as $book)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $book->kind }}</td>
                        <td><code class="font-mono text-pine">{{ $book->version }}</code></td>
                        <td>{{ $book->title }}</td>
                        <td><span class="badge badge-gray">{{ $book->status }}</span></td>
                        <td>{{ $book->items_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><p class="text-sm text-gray-400 italic py-4">No knowledge book seeded.</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
