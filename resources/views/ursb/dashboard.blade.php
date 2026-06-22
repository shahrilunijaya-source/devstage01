<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>URSB — Phase 1 Foundation</title>
<style>
    :root {
        --bg: #0f1117; --panel: #171a23; --line: #262b38; --text: #e6e9f0;
        --muted: #8b93a7; --accent: #00a6a6; --primary: #2f75b5; --gold: #d6a14a;
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--text);
        font: 15px/1.55 ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
    header { padding: 28px 32px; border-bottom: 1px solid var(--line);
        background: linear-gradient(180deg, #11141c, #0f1117); }
    header h1 { margin: 0; font-size: 22px; letter-spacing: .3px; }
    header p { margin: 6px 0 0; color: var(--muted); font-size: 13px; }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 32px 64px; }
    .cards { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 28px; }
    .card { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 14px; }
    .card .n { font-size: 26px; font-weight: 650; }
    .card .l { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .6px; }
    section { margin-bottom: 32px; }
    h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .8px; color: var(--muted);
        border-bottom: 1px solid var(--line); padding-bottom: 8px; margin: 0 0 16px; }
    .tenant { font-weight: 650; margin: 14px 0 4px; }
    .tenant .badge { color: var(--accent); font-weight: 500; font-size: 12px; }
    .proj { margin: 6px 0 6px 16px; padding-left: 14px; border-left: 2px solid var(--line); }
    .proj .name { color: var(--primary); font-weight: 600; }
    .mod { margin: 8px 0 8px 14px; color: var(--text); }
    .stages { display: flex; flex-wrap: wrap; gap: 6px; margin: 6px 0 0; }
    .pill { font-size: 11px; padding: 3px 9px; border-radius: 999px; border: 1px solid var(--line);
        color: var(--muted); background: #12151d; }
    .pill.baselined { color: #0f1117; background: var(--accent); border-color: var(--accent); font-weight: 600; }
    .chain { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .node { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; max-width: 260px; }
    .node .ref { color: var(--gold); font-weight: 650; font-size: 13px; font-family: ui-monospace, monospace; }
    .node .ti { font-size: 12.5px; color: var(--text); margin-top: 3px; }
    .node .st { font-size: 10.5px; color: var(--muted); margin-top: 4px; text-transform: uppercase; letter-spacing: .5px; }
    .arrow { color: var(--accent); font-size: 18px; }
    table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); }
    th { color: var(--muted); font-weight: 500; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
    .empty { color: var(--muted); font-style: italic; }
    code { font-family: ui-monospace, monospace; color: var(--accent); }
</style>
</head>
<body>
<header>
    <h1>URSB — Requirement-to-Prototype Platform</h1>
    <p>Phase 1 Foundation · live canonical model · read-only verification view</p>
</header>
<div class="wrap">

    <div class="cards">
        @foreach (['tenants' => 'Tenants', 'objects' => 'Objects', 'traces' => 'Trace edges', 'roles' => 'ACL roles', 'permissions' => 'Permissions', 'bindings' => 'Bindings'] as $key => $label)
            <div class="card"><div class="n">{{ $counts[$key] }}</div><div class="l">{{ $label }}</div></div>
        @endforeach
    </div>

    <section>
        <h2>Portfolio hierarchy — Tenant › Project › Module › Stage</h2>
        @forelse ($tenants as $tenant)
            <div class="tenant">{{ $tenant->name }} <span class="badge">{{ $tenant->slug }} · {{ $tenant->type }}</span></div>
            @foreach ($tenant->projects as $project)
                <div class="proj">
                    <span class="name">{{ $project->name }}</span> <span class="pill">{{ $project->code }}</span>
                    @foreach ($project->modules as $module)
                        <div class="mod">▸ {{ $module->name }} <span class="pill">{{ $module->code }}</span>
                            <div class="stages">
                                @foreach ($module->stages->sortBy(fn ($s) => $s->stage->order()) as $stage)
                                    <span class="pill {{ $stage->status === 'baselined' ? 'baselined' : '' }}">{{ $stage->stage->label() }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @empty
            <p class="empty">No tenants yet. Run <code>php artisan db:seed --class=UrsbDemoSeeder</code>.</p>
        @endforelse
    </section>

    <section>
        <h2>Traceability chain — evidence → finding → requirement</h2>
        @if ($chain->isEmpty())
            <p class="empty">No objects yet.</p>
        @else
            <div class="chain">
                @foreach ($chain as $obj)
                    <div class="node">
                        <div class="ref">{{ $obj->ref }}</div>
                        <div class="ti">{{ $obj->title }}</div>
                        <div class="st">{{ $obj->status->label() }}</div>
                    </div>
                    @if (! $loop->last)<span class="arrow">→</span>@endif
                @endforeach
            </div>
        @endif
    </section>

    <section>
        <h2>Knowledge books</h2>
        <table>
            <thead><tr><th>Kind</th><th>Version</th><th>Title</th><th>Status</th><th>Items</th></tr></thead>
            <tbody>
            @forelse ($books as $book)
                <tr><td>{{ $book->kind }}</td><td><code>{{ $book->version }}</code></td>
                    <td>{{ $book->title }}</td><td>{{ $book->status }}</td><td>{{ $book->items_count }}</td></tr>
            @empty
                <tr><td colspan="5" class="empty">No knowledge book seeded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

</div>
</body>
</html>
