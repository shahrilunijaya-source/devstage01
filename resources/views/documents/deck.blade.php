<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $baseline->version_label }} — Review Deck</title>
    <style>
        :root {
            --bg: #0e1118; --surface: #161b25; --line: #28303f; --text: #eef2f8;
            --muted: #8b95a8; --accent: #4f8cff; --good: #3fae6e; --warn: #e0a72f; --bad: #d65a5a;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--bg); color: var(--text); font: 16px/1.55 system-ui, -apple-system, Segoe UI, sans-serif; }

        .deck { }
        .slide {
            min-height: 100vh; padding: 7vh 9vw; display: flex; flex-direction: column;
            justify-content: center; border-bottom: 1px solid var(--line); position: relative;
        }
        .slide .kicker { color: var(--accent); font-size: 14px; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 18px; }
        .slide h1 { font-size: clamp(32px, 5vw, 64px); line-height: 1.05; letter-spacing: -1px; }
        .slide h2 { font-size: clamp(24px, 3.4vw, 40px); letter-spacing: -.5px; margin-bottom: 26px; }
        .slide .meta { color: var(--muted); margin-top: 26px; font-size: 16px; }
        .slide .pagenum { position: absolute; bottom: 24px; right: 9vw; color: var(--muted); font-size: 13px; }

        .stats { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 30px; }
        .stat { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 20px 26px; min-width: 150px; }
        .stat .n { font-size: 40px; font-weight: 700; }
        .stat .l { color: var(--muted); font-size: 13px; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }

        .items { display: grid; gap: 12px; margin-top: 6px; }
        .item { background: var(--surface); border: 1px solid var(--line); border-left: 3px solid var(--accent); border-radius: 8px; padding: 14px 18px; }
        .item.risk { border-left-color: var(--warn); }
        .item .ref { font-family: ui-monospace, Menlo, monospace; font-size: 12px; color: var(--accent); }
        .item.risk .ref { color: var(--warn); }
        .item .t { font-weight: 600; margin: 2px 0; }
        .item .b { color: var(--muted); font-size: 14px; }
        .item .s { display: inline-block; margin-top: 6px; font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }

        .nav { position: fixed; bottom: 18px; left: 18px; display: flex; gap: 8px; z-index: 10; }
        .nav button { background: var(--surface); color: var(--text); border: 1px solid var(--line); border-radius: 8px; padding: 8px 14px; cursor: pointer; font-size: 14px; }
        .nav button:hover { border-color: var(--accent); }
        .nav .count { color: var(--muted); align-self: center; font-size: 13px; padding: 0 6px; }
        .topbar { position: fixed; top: 14px; right: 18px; z-index: 10; }
        .topbar a { color: var(--muted); text-decoration: none; font-size: 13px; border: 1px solid var(--line); padding: 6px 12px; border-radius: 8px; }
        .topbar a:hover { color: var(--text); border-color: var(--accent); }

        @media print {
            .nav, .topbar { display: none; }
            .slide { min-height: auto; page-break-after: always; border: none; padding: 40px; }
        }
    </style>
</head>
<body>
<div class="topbar">
    <a href="{{ route('baselines.deck.pptx', $baseline) }}">Download .pptx</a>
    <a href="{{ route('baselines.show', $baseline) }}">← Back to document</a>
</div>

<div class="deck" id="deck">
    {{-- Title slide --}}
    <section class="slide">
        <div class="kicker">Stage Review Deck</div>
        <h1>{{ $baseline->version_label }}</h1>
        <div class="meta">
            {{ $project->tenant?->name }} · {{ $project->name }} · {{ $baseline->stage->module->name }}<br>
            {{ $stageLabel }} stage · Knowledge Book {{ $baseline->knowledge_book_version ?? '—' }}<br>
            Frozen {{ optional($baseline->approved_at)->toDayDateTimeString() ?? '—' }}
        </div>
    </section>

    {{-- Summary slide --}}
    <section class="slide">
        <div class="kicker">At a glance</div>
        <h2>Baseline summary</h2>
        <div class="stats">
            <div class="stat"><div class="n">{{ $summary['total'] }}</div><div class="l">Objects</div></div>
            <div class="stat"><div class="n">{{ $summary['requirements'] }}</div><div class="l">Requirements</div></div>
            <div class="stat"><div class="n" style="color:{{ $summary['risks'] > 0 ? 'var(--warn)' : 'inherit' }};">{{ $summary['risks'] }}</div><div class="l">Open risks</div></div>
            <div class="stat"><div class="n" style="color:var(--good);">{{ $summary['confirmed'] }}</div><div class="l">Evidence-confirmed</div></div>
            <div class="stat"><div class="n">{{ $summary['types'] }}</div><div class="l">Object types</div></div>
        </div>
    </section>

    {{-- One slide per content section --}}
    @foreach ($sections as $section)
        <section class="slide">
            <div class="kicker">{{ $stageLabel }} · {{ $section['items']->count() }} item{{ $section['items']->count() === 1 ? '' : 's' }}</div>
            <h2>{{ $section['heading'] }}</h2>
            <div class="items">
                @foreach ($section['items']->take(6) as $item)
                    <div class="item">
                        <div class="ref">{{ $item['ref'] }}</div>
                        <div class="t">{{ $item['title'] }}</div>
                        @if ($item['body'])<div class="b">{{ \Illuminate\Support\Str::limit($item['body'], 160) }}</div>@endif
                        @if ($item['status'])<span class="s">{{ str_replace('_', ' ', $item['status']) }}</span>@endif
                    </div>
                @endforeach
                @if ($section['items']->count() > 6)
                    <div class="b" style="color:var(--muted);">+ {{ $section['items']->count() - 6 }} more in the full document</div>
                @endif
            </div>
        </section>
    @endforeach

    {{-- Risks slide --}}
    @if ($risks->isNotEmpty())
        <section class="slide">
            <div class="kicker">Watch list</div>
            <h2>Open risks ({{ $risks->count() }})</h2>
            <div class="items">
                @foreach ($risks->take(6) as $risk)
                    <div class="item risk">
                        <div class="ref">{{ $risk['ref'] }}</div>
                        <div class="t">{{ $risk['title'] }}</div>
                        @if ($risk['body'])<div class="b">{{ \Illuminate\Support\Str::limit($risk['body'], 160) }}</div>@endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Closing slide --}}
    <section class="slide">
        <div class="kicker">End of review</div>
        <h1>Approve this baseline?</h1>
        <div class="meta">{{ $baseline->version_label }} · {{ $summary['total'] }} objects · {{ $summary['risks'] }} open risk{{ $summary['risks'] === 1 ? '' : 's' }}<br>Generated from the canonical model — every slide traces to a pinned object version.</div>
    </section>
</div>

<div class="nav">
    <button id="prev" aria-label="Previous slide">←</button>
    <span class="count" id="count"></span>
    <button id="next" aria-label="Next slide">→</button>
</div>

<script>
    (function () {
        const slides = Array.from(document.querySelectorAll('.slide'));
        let i = 0;
        const count = document.getElementById('count');
        slides.forEach((s, n) => {
            const tag = document.createElement('div');
            tag.className = 'pagenum';
            tag.textContent = (n + 1) + ' / ' + slides.length;
            s.appendChild(tag);
        });
        function go(n) {
            i = Math.max(0, Math.min(slides.length - 1, n));
            slides[i].scrollIntoView({ behavior: 'smooth' });
            count.textContent = (i + 1) + ' / ' + slides.length;
        }
        document.getElementById('next').onclick = () => go(i + 1);
        document.getElementById('prev').onclick = () => go(i - 1);
        document.addEventListener('keydown', (e) => {
            if (['ArrowRight', 'PageDown', ' '].includes(e.key)) { e.preventDefault(); go(i + 1); }
            if (['ArrowLeft', 'PageUp'].includes(e.key)) { e.preventDefault(); go(i - 1); }
        });
        count.textContent = '1 / ' + slides.length;
    })();
</script>
</body>
</html>
