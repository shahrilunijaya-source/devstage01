@extends('layouts.ursb')
@section('title', $session->title)
@section('content')
    <p class="sub" style="margin-bottom:6px;">
        <a href="{{ route('portfolio.show', $session->project) }}">← {{ $session->project->name }}</a>
    </p>
    <h1 class="page">{{ $session->title }}</h1>
    <p class="sub">
        {{ $session->module->name }} · {{ $session->stage->stage->label() }} ·
        {{ collect([$session->process, $session->domain, $session->location])->filter()->implode(' · ') ?: 'no dimension' }}
    </p>

    @php($phases = ['pre_analysis' => 'AI Pre-analysis', 'firewall_review' => 'Quality Firewall', 'ready' => 'Ready', 'in_session' => 'In Session', 'post_session' => 'Post-session', 'approved' => 'Approved'])
    <section>
        <h2 class="sec"><span>Five-phase lifecycle</span></h2>
        <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
            @foreach ($phases as $key => $label)
                <span class="pill {{ $session->phase === $key ? 'on' : '' }}">{{ $label }}</span>
                @if (! $loop->last)<span style="color:var(--muted)">→</span>@endif
            @endforeach
        </div>

        <div class="panel" style="margin-top:14px;">
            @if ($session->phase === 'pre_analysis' && $canEdit)
                <form method="POST" action="{{ route('sessions.preanalyze', $session) }}">@csrf
                    <button class="btn" type="submit">Run AI pre-analysis</button>
                    <span class="sub" style="display:inline; margin-left:10px;">Drafts findings &amp; requirements from evidence, trace-linked.</span>
                </form>
            @elseif ($session->phase === 'firewall_review')
                @if ($canValidate)
                    <form method="POST" action="{{ route('sessions.firewall', $session) }}">@csrf
                        <button class="btn" type="submit">Pass quality firewall</button>
                        <span class="sub" style="display:inline; margin-left:10px;">Mandatory human review before client presentation (PRD §9.3.2).</span>
                    </form>
                @else
                    <p class="sub" style="margin:0;">Awaiting a reviewer with <code>validate</code> rights to pass the quality firewall.</p>
                @endif
            @elseif ($session->phase === 'ready' && $canEdit)
                <form method="POST" action="{{ route('sessions.start', $session) }}">@csrf
                    <button class="btn" type="submit">Start evidence-led session</button>
                </form>
            @elseif ($session->phase === 'in_session')
                <p class="sub" style="margin:0 0 10px;">Session in progress — capture client responses on each item below.</p>
                @if ($canEdit)
                    <form method="POST" action="{{ route('sessions.consolidate', $session) }}">@csrf
                        <button class="btn" type="submit">Consolidate &amp; close session</button>
                    </form>
                @endif
            @elseif ($session->phase === 'post_session')
                @if ($canApprove)
                    <form method="POST" action="{{ route('sessions.approve', $session) }}">@csrf
                        <button class="btn" type="submit">Approve session</button>
                        <span class="sub" style="display:inline; margin-left:10px;">All items must be resolved (PRD §9.7 exit gate).</span>
                    </form>
                @else
                    <p class="sub" style="margin:0;">Consolidated — awaiting an approver.</p>
                @endif
            @else
                <p class="sub" style="margin:0;">Phase: <strong>{{ $phases[$session->phase] ?? $session->phase }}</strong></p>
            @endif
        </div>
    </section>

    @if ($session->phase === 'pre_analysis' && $canEdit)
        <section>
            <h2 class="sec"><span>Evidence intake</span></h2>
            <p class="sub">Evidence enters as immutable source (PRD §8). Paste text or upload a file before running AI pre-analysis.</p>
            <form class="panel" method="POST" action="{{ route('sessions.evidence.store', $session) }}" enctype="multipart/form-data">@csrf
                <div class="row">
                    <div>
                        <label>Label</label>
                        <input type="text" name="label" maxlength="255" required placeholder="e.g. Stakeholder interview — payroll lead">
                    </div>
                    <div>
                        <label>Classification</label>
                        <select name="classification">
                            <option value="internal">internal</option>
                            <option value="public">public</option>
                            <option value="confidential">confidential</option>
                            <option value="restricted">restricted</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Source</label>
                        <select name="source_type" id="ev-source" onchange="document.getElementById('ev-text').style.display=this.value==='paste'?'block':'none';document.getElementById('ev-file').style.display=this.value==='file'?'block':'none';">
                            <option value="paste">Paste text</option>
                            <option value="file">Upload file</option>
                        </select>
                    </div>
                    <div></div>
                </div>
                <div id="ev-text">
                    <label>Evidence text</label>
                    <textarea name="text" rows="4" placeholder="Paste the raw source — transcript, email, requirement note…"></textarea>
                </div>
                <div id="ev-file" style="display:none;">
                    <label>File (max 10 MB)</label>
                    <input type="file" name="file">
                </div>
                <div style="margin-top:16px;"><button class="btn" type="submit">Capture evidence</button></div>
            </form>
        </section>
    @endif

    <section>
        <h2 class="sec"><span>Items ({{ $objects->count() }})</span></h2>
        @if ($objects->isEmpty())
            <p class="empty">No objects yet. Run AI pre-analysis, or attach evidence to this session.</p>
        @else
            <table>
                <thead><tr><th>Ref</th><th>Type</th><th>Title</th><th>Status</th><th>Conf.</th><th>Impact</th>
                    @if ($session->phase === 'in_session' && $canEdit)<th>Capture</th>@endif</tr></thead>
                <tbody>
                @foreach ($objects as $obj)
                    <tr>
                        <td><a href="{{ route('objects.show', $obj) }}"><code>{{ $obj->ref }}</code></a></td>
                        <td class="sub" style="margin:0;">{{ str_replace('_', ' ', $obj->type->value) }}</td>
                        <td>{{ $obj->title }}</td>
                        <td><span class="pill {{ $obj->status->value === 'confirmed_by_evidence' ? 'on' : '' }}">{{ $obj->status->label() }}</span></td>
                        <td>{{ $obj->confidence?->value ?? '—' }}</td>
                        <td>{{ $obj->impact ?? '—' }}</td>
                        @if ($session->phase === 'in_session' && $canEdit)
                            <td>
                                <form class="inline" method="POST" action="{{ route('sessions.capture', [$session, $obj]) }}">@csrf
                                    @foreach (['confirm', 'correct', 'complete', 'decide'] as $d)
                                        <button class="btn ghost sm" name="decision" value="{{ $d }}" type="submit" style="margin:1px;">{{ ucfirst($d) }}</button>
                                    @endforeach
                                </form>
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section>
        <h2 class="sec"><span>Question bank — {{ $session->stage->stage->label() }} (from pinned Knowledge Book)</span></h2>
        @if ($questions->isEmpty())
            <p class="empty">No question bank — project not pinned to a Knowledge Book version.</p>
        @else
            <div class="panel">
                <ol style="margin:0; padding-left:20px;">
                    @foreach ($questions as $q)<li style="margin:4px 0;">{{ $q->title }}</li>@endforeach
                </ol>
            </div>
        @endif
    </section>
@endsection
