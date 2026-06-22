@extends('layouts.ursb')
@section('title', $session->title)
@section('content')
    <p class="text-[13px] text-gray-500 mb-2">
        <a href="{{ route('portfolio.show', $session->project) }}" class="hover:text-teal">← {{ $session->project->name }}</a>
    </p>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">{{ $session->title }}</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">
                {{ $session->module->name }} · {{ $session->stage->stage->label() }} ·
                {{ collect([$session->process, $session->domain, $session->location])->filter()->implode(' · ') ?: 'no dimension' }}
            </p>
        </div>
    </div>

    @php($phases = ['pre_analysis' => 'AI Pre-analysis', 'firewall_review' => 'Quality Firewall', 'ready' => 'Ready', 'in_session' => 'In Session', 'post_session' => 'Post-session', 'approved' => 'Approved'])
    <div class="mb-6">
        <h2 class="section-title mb-2">Five-phase lifecycle</h2>
        <div class="flex flex-wrap gap-2 items-center">
            @foreach ($phases as $key => $label)
                <span class="badge {{ $session->phase === $key ? 'badge-teal' : 'badge-gray' }}">{{ $label }}</span>
                @if (! $loop->last)<span class="text-gray-300">→</span>@endif
            @endforeach
        </div>

        <div class="card card-pad mt-4">
            @if ($session->phase === 'pre_analysis' && $canEdit)
                <form method="POST" action="{{ route('sessions.preanalyze', $session) }}" class="flex flex-wrap items-center gap-3">@csrf
                    <button class="btn-primary" type="submit">Run AI pre-analysis</button>
                    <span class="text-[13px] text-gray-500">Drafts findings &amp; requirements from evidence, trace-linked.</span>
                </form>
            @elseif ($session->phase === 'firewall_review')
                @if ($canValidate)
                    <form method="POST" action="{{ route('sessions.firewall', $session) }}" class="flex flex-wrap items-center gap-3">@csrf
                        <button class="btn-primary" type="submit">Pass quality firewall</button>
                        <span class="text-[13px] text-gray-500">Mandatory human review before client presentation (PRD §9.3.2).</span>
                    </form>
                @else
                    <p class="text-sm text-gray-500">Awaiting a reviewer with <code class="font-mono text-pine">validate</code> rights to pass the quality firewall.</p>
                @endif
            @elseif ($session->phase === 'ready' && $canEdit)
                <form method="POST" action="{{ route('sessions.start', $session) }}">@csrf
                    <button class="btn-primary" type="submit">Start evidence-led session</button>
                </form>
            @elseif ($session->phase === 'in_session')
                <p class="text-[13px] text-gray-500 mb-3">Session in progress — capture client responses on each item below.</p>
                @if ($canEdit)
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('sessions.scan-conflicts', $session) }}">@csrf
                            <button class="btn-secondary" type="submit">Scan for conflicts</button>
                        </form>
                        <form method="POST" action="{{ route('sessions.consolidate', $session) }}">@csrf
                            <button class="btn-pine" type="submit">Consolidate &amp; close session</button>
                        </form>
                    </div>
                    <p class="text-[12px] text-gray-400 mt-2">Conflict scan flags duplicate-intent requirements (PRD §9); flagged items block approval until reconciled.</p>
                @endif
            @elseif ($session->phase === 'post_session')
                @if ($canApprove)
                    <form method="POST" action="{{ route('sessions.approve', $session) }}" class="flex flex-wrap items-center gap-3">@csrf
                        <button class="btn-primary" type="submit">Approve session</button>
                        <span class="text-[13px] text-gray-500">All items must be resolved (PRD §9.7 exit gate).</span>
                    </form>
                @else
                    <p class="text-sm text-gray-500">Consolidated — awaiting an approver.</p>
                @endif
            @else
                <p class="text-sm text-gray-500">Phase: <strong class="text-gray-900">{{ $phases[$session->phase] ?? $session->phase }}</strong></p>
            @endif
        </div>
    </div>

    @if ($session->phase === 'pre_analysis' && $canEdit)
        <div class="mb-6">
            <h2 class="section-title mb-2">Evidence intake</h2>
            <p class="text-[13px] text-gray-500 mb-3">Evidence enters as immutable source (PRD §8). Paste text or upload a file before running AI pre-analysis.</p>
            <form class="card card-pad" method="POST" action="{{ route('sessions.evidence.store', $session) }}" enctype="multipart/form-data">@csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Label</label>
                        <input type="text" name="label" maxlength="255" required placeholder="e.g. Stakeholder interview — payroll lead" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Classification</label>
                        <select name="classification" class="form-select">
                            <option value="internal">internal</option>
                            <option value="public">public</option>
                            <option value="confidential">confidential</option>
                            <option value="restricted">restricted</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="form-label">Source</label>
                        <select name="source_type" id="ev-source" class="form-select" onchange="document.getElementById('ev-text').style.display=this.value==='paste'?'block':'none';document.getElementById('ev-file').style.display=this.value==='file'?'block':'none';">
                            <option value="paste">Paste text</option>
                            <option value="file">Upload file</option>
                        </select>
                    </div>
                    <div></div>
                </div>
                <div id="ev-text" class="mt-4">
                    <label class="form-label">Evidence text</label>
                    <textarea name="text" rows="4" placeholder="Paste the raw source — transcript, email, requirement note…" class="form-textarea"></textarea>
                </div>
                <div id="ev-file" class="mt-4" style="display:none;">
                    <label class="form-label">File (max 10 MB)</label>
                    <input type="file" name="file" class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-[10px] file:border-0 file:bg-teal file:text-white file:font-medium file:text-sm hover:file:bg-teal-700">
                </div>
                <div class="mt-4"><button class="btn-primary" type="submit">Capture evidence</button></div>
            </form>
        </div>
    @endif

    <div class="mb-6">
        <h2 class="section-title mb-2">Items ({{ $objects->count() }})</h2>
        @if ($objects->isEmpty())
            <p class="text-sm text-gray-400 italic">No objects yet. Run AI pre-analysis, or attach evidence to this session.</p>
        @else
            <div class="card overflow-hidden">
                <table class="data-table">
                    <thead><tr><th>Ref</th><th>Type</th><th>Title</th><th>Status</th><th>Conf.</th><th>Impact</th>
                        @if ($session->phase === 'in_session' && $canEdit)<th>Capture</th>@endif</tr></thead>
                    <tbody>
                    @foreach ($objects as $obj)
                        <tr>
                            <td><a href="{{ route('objects.show', $obj) }}" class="font-mono text-teal hover:text-teal-700">{{ $obj->ref }}</a></td>
                            <td class="text-gray-500">{{ str_replace('_', ' ', $obj->type->value) }}</td>
                            <td class="text-gray-900">{{ $obj->title }}</td>
                            <td><span class="badge {{ $obj->status->value === 'confirmed_by_evidence' ? 'badge-teal' : 'badge-gray' }}">{{ $obj->status->label() }}</span></td>
                            <td class="text-gray-500">{{ $obj->confidence?->value ?? '—' }}</td>
                            <td class="text-gray-500">{{ $obj->impact ?? '—' }}</td>
                            @if ($session->phase === 'in_session' && $canEdit)
                                <td>
                                    <form method="POST" action="{{ route('sessions.capture', [$session, $obj]) }}" class="flex flex-wrap gap-1">@csrf
                                        @foreach (['confirm', 'correct', 'complete', 'decide'] as $d)
                                            <button class="btn-secondary !py-1 !px-2.5 !text-[12px]" name="decision" value="{{ $d }}" type="submit">{{ ucfirst($d) }}</button>
                                        @endforeach
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mb-6">
        <h2 class="section-title mb-2">Question bank — {{ $session->stage->stage->label() }} (from pinned Knowledge Book)</h2>
        @if ($questions->isEmpty())
            <p class="text-sm text-gray-400 italic">No question bank — project not pinned to a Knowledge Book version.</p>
        @else
            <div class="card card-pad">
                <ol class="list-decimal pl-5 space-y-1 text-sm text-gray-800">
                    @foreach ($questions as $q)<li>{{ $q->title }}</li>@endforeach
                </ol>
            </div>
        @endif
    </div>
@endsection
