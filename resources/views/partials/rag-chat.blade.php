{{--
    Shared RAG chat panel — used by per-project chat and portfolio chat.
    Required: $askUrl, $initial (array of prior messages)
    Optional: $title, $sub, $canSync (bool), $syncUrl, $statusUrl, $examples (array of strings)
--}}
@php
    $canSync   = $canSync   ?? false;
    $syncUrl   = $syncUrl   ?? '';
    $statusUrl = $statusUrl ?? '';
    $title     = $title     ?? 'Ask Track AI';
    $sub       = $sub       ?? 'Answers cite their source. If it’s not in the data, the assistant says so.';
    // Starter questions — tappable, work for most projects. Pass $examples to override.
    $examples  = $examples ?? [
        'What were last week’s blockers?',
        'When is the next claim due?',
        'How is the team’s morale?',
        'List the open issues',
        'Are we behind schedule?',
        'What does the contract say about the bond?',
    ];
@endphp

<div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-5" x-data="ragChat({
        askUrl: '{{ $askUrl }}',
        syncUrl: '{{ $syncUrl }}',
        statusUrl: '{{ $statusUrl }}',
        token: '{{ csrf_token() }}',
        canSync: {{ $canSync ? 'true' : 'false' }},
        initial: @js($initial),
        examples: @js(array_values($examples))
    })">

    {{-- ── Chat column ───────────────────────────────────────────── --}}
    <div class="lg:col-span-2">
    <x-card>
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <div>
                <p class="text-[13px] font-semibold text-gray-700">{{ $title }}</p>
                <p class="text-[11px] text-gray-400">{{ $sub }}</p>
            </div>
            <template x-if="canSync">
                <button @click="sync()" :disabled="syncing" class="btn-secondary text-[12px]"
                    x-text="syncing ? 'Syncing…' : 'Sync Drive docs'"></button>
            </template>
        </div>

        {{-- ── Knowledge base status: summary bar + expandable doc list ── --}}
        <div x-show="docs.length" x-cloak class="border-b border-gray-100">
            <button type="button" @click="showDocs = !showDocs"
                class="w-full flex items-center justify-between gap-3 px-5 py-2.5 hover:bg-gray-50 transition text-left">
                <div class="flex items-center gap-2.5 text-[11px] flex-wrap">
                    <span class="font-semibold text-gray-600">Knowledge base</span>
                    <span class="inline-flex items-center gap-1 text-emerald-600">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span><span x-text="stats.indexed"></span> indexed
                    </span>
                    <template x-if="stats.pending">
                        <span class="inline-flex items-center gap-1 text-amber-600">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span><span x-text="stats.pending"></span> pending
                        </span>
                    </template>
                    <template x-if="stats.error">
                        <span class="inline-flex items-center gap-1 text-rose-600 font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span><span x-text="stats.error"></span> error
                        </span>
                    </template>
                    <template x-if="stats.skipped">
                        <span class="text-gray-400"><span x-text="stats.skipped"></span> skipped</span>
                    </template>
                    <template x-if="stats.lastIndexed">
                        <span class="text-gray-300">· last synced <span x-text="fmtDate(stats.lastIndexed)"></span></span>
                    </template>
                </div>
                <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform" :class="showDocs && 'rotate-180'"
                     viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
            </button>

            <div x-show="showDocs" x-cloak class="px-5 pb-3 pt-1 max-h-56 overflow-y-auto">
                <template x-for="d in sortedDocs" :key="d.name">
                    <div class="flex items-start justify-between gap-3 py-1.5 border-b border-gray-50 last:border-0">
                        <div class="flex items-start gap-2 min-w-0">
                            <span class="w-1.5 h-1.5 rounded-full mt-1.5 shrink-0" :class="docStatusMeta(d).dot"></span>
                            <div class="min-w-0">
                                <p class="text-[12px] text-gray-700 truncate" x-text="d.name"></p>
                                <p x-show="d.error" class="text-[10px] text-rose-500 mt-0.5 break-words" x-text="d.error"></p>
                            </div>
                        </div>
                        <span class="text-[10px] text-gray-400 shrink-0 whitespace-nowrap" x-text="docMeta(d)"></span>
                    </div>
                </template>
            </div>
        </div>

        <div x-ref="scroll" class="px-5 py-4 space-y-4 overflow-y-auto" style="height:55vh">
            {{-- Friendly empty state with tappable starters --}}
            <template x-if="!messages.length">
                <div class="text-center py-12 px-4">
                    <div class="w-12 h-12 mx-auto rounded-full bg-teal/10 flex items-center justify-center mb-3">
                        <x-heroicon-o-chat-bubble-left-right class="w-6 h-6 text-teal"/>
                    </div>
                    <p class="text-[14px] font-semibold text-gray-700">Ask me about this project</p>
                    <p class="text-[12px] text-gray-400 mt-1 max-w-sm mx-auto">
                        I read your documents and project data and answer in plain language.
                        Not sure where to start? Tap one:
                    </p>
                    <div class="flex flex-wrap justify-center gap-2 mt-4">
                        <template x-for="ex in examples" :key="ex">
                            <button @click="ask(ex)"
                                class="px-3 py-1.5 rounded-full bg-gray-50 hover:bg-teal/10 border border-gray-200 hover:border-teal/40 text-[12px] text-gray-600 hover:text-teal transition"
                                x-text="ex"></button>
                        </template>
                    </div>
                </div>
            </template>

            <template x-for="(m, i) in messages" :key="i">
                <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="m.role === 'user'
                            ? 'bg-teal text-white rounded-2xl rounded-br-sm px-4 py-2 max-w-[80%]'
                            : (m.grounded === false
                                ? 'bg-amber-50 text-amber-800 border border-amber-200 rounded-2xl rounded-bl-sm px-4 py-2 max-w-[85%]'
                                : 'bg-gray-100 text-gray-800 rounded-2xl rounded-bl-sm px-4 py-2 max-w-[85%]')">
                        <p class="text-[13px] whitespace-pre-wrap" x-text="m.content"></p>

                        {{-- "Couldn't find it" hint so a vague question never dead-ends --}}
                        <template x-if="m.role !== 'user' && m.grounded === false">
                            <p class="text-[11px] text-amber-700/80 mt-1.5 pt-1.5 border-t border-amber-200/60">
                                Tip: name the document, claim or issue you mean, or add a date — then try again.
                            </p>
                        </template>

                        {{-- Colour-coded, plain-language source tags --}}
                        <template x-if="m.citations && m.citations.length">
                            <div class="mt-2 flex flex-wrap gap-1">
                                <template x-for="c in m.citations" :key="c.source_label + c.score">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border text-[10px] font-medium"
                                          :class="sourceMeta(c).cls">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="sourceMeta(c).dot"></span>
                                        <span x-text="sourceMeta(c).label"></span>
                                    </span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="loading">
                <div class="flex justify-start">
                    <div class="bg-gray-100 text-gray-400 rounded-2xl rounded-bl-sm px-4 py-2 text-[13px]">Thinking…</div>
                </div>
            </template>
        </div>

        <div class="border-t border-gray-100 px-5 py-3">
            <div class="flex items-end gap-2">
                <textarea x-model="input" @keydown.enter.prevent="send()" rows="1"
                    placeholder="Ask a question…" class="form-input text-[13px] resize-none flex-1"></textarea>
                <button @click="send()" :disabled="loading || !input.trim()" class="btn-primary text-[12px]">Send</button>
            </div>
            <div class="flex items-center gap-2 mt-2">
                <label class="flex items-center gap-1.5 text-[11px] text-gray-500 cursor-pointer">
                    <input type="checkbox" :checked="model==='sonnet'" @change="model = $event.target.checked ? 'sonnet' : 'haiku'"
                        class="rounded border-gray-300 text-teal focus:ring-teal">
                    Deeper reasoning (slower, costs more)
                </label>
            </div>
        </div>
    </x-card>
    </div>

    {{-- ── Guidance side panel ───────────────────────────────────── --}}
    <div class="lg:col-span-1">
        <x-card>
            <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2">
                <x-heroicon-o-light-bulb class="w-4 h-4 text-amber-500"/>
                <p class="text-[13px] font-semibold text-gray-700">How this works</p>
            </div>

            <div class="p-5 space-y-5">
                <p class="text-[12px] text-gray-600 leading-relaxed">
                    Ask in plain words, like you’d ask a teammate. I only answer from
                    <strong class="text-gray-700">the documents and data you have access to</strong> —
                    I never guess or make things up.
                </p>

                {{-- What it can answer — colour legend --}}
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-2">What I can answer</p>
                    <ul class="space-y-2">
                        <li class="flex items-start gap-2">
                            <span class="w-2 h-2 rounded-full bg-blue-500 mt-1.5 shrink-0"></span>
                            <span class="text-[12px] text-gray-600"><strong class="text-gray-700">Documents</strong> — SST, contract, BRS, SDS and other files in the project’s Drive folder</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 mt-1.5 shrink-0"></span>
                            <span class="text-[12px] text-gray-600"><strong class="text-gray-700">Live project data</strong> — issues, claims, progress vs plan, money, team morale</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-2 h-2 rounded-full bg-purple-500 mt-1.5 shrink-0"></span>
                            <span class="text-[12px] text-gray-600"><strong class="text-gray-700">Weekly updates & notes</strong> — what the PM/PE logged each week</span>
                        </li>
                    </ul>
                </div>

                {{-- Tappable starter questions --}}
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-2">Try asking</p>
                    <div class="space-y-1.5">
                        <template x-for="ex in examples" :key="ex">
                            <button @click="ask(ex)"
                                class="w-full text-left flex items-center gap-2 px-2.5 py-1.5 rounded-lg hover:bg-teal/5 text-[12px] text-gray-600 hover:text-teal transition group">
                                <x-heroicon-o-arrow-right-circle class="w-3.5 h-3.5 text-gray-300 group-hover:text-teal shrink-0"/>
                                <span x-text="ex"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Tips for better answers --}}
                <div class="rounded-xl bg-gray-50 border border-gray-100 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-2">Get better answers</p>
                    <ul class="space-y-1.5 text-[11px] text-gray-500">
                        <li class="flex gap-1.5"><span class="text-teal">•</span> Name the thing you mean (a document, a claim, an issue).</li>
                        <li class="flex gap-1.5"><span class="text-teal">•</span> Add a date or week if it matters.</li>
                        <li class="flex gap-1.5"><span class="text-teal">•</span> Ask one thing at a time.</li>
                    </ul>
                    <p class="text-[11px] text-gray-500 mt-2.5 pt-2.5 border-t border-gray-200/70">
                        If I can’t find it, I’ll say so — that’s not an error. Just rephrase or add a detail and ask again.
                    </p>
                </div>
            </div>
        </x-card>
    </div>
</div>

@push('scripts')
<script>
function ragChat(cfg) {
    return {
        messages: cfg.initial || [],
        examples: cfg.examples || [],
        docs: [],
        input: '',
        model: 'haiku',
        loading: false,
        syncing: false,
        showDocs: false,
        canSync: cfg.canSync,

        init() { this.scroll(); if (this.canSync) this.pollStatus(); },

        // ── Knowledge-base status helpers ──────────────────────────
        get stats() {
            const s = { indexed: 0, pending: 0, skipped: 0, error: 0, total: this.docs.length, lastIndexed: null };
            for (const d of this.docs) {
                if (d.status === 'indexed') {
                    s.indexed++;
                    if (d.indexed_at && (!s.lastIndexed || d.indexed_at > s.lastIndexed)) s.lastIndexed = d.indexed_at;
                } else if (d.status === 'pending') {
                    s.pending++;
                } else if (d.status === 'error') {
                    s.error++;
                } else {
                    s.skipped++; // unindexable (folders, unsupported types)
                }
            }
            return s;
        },
        get sortedDocs() {
            const rank = { error: 0, pending: 1, indexed: 2 };
            return [...this.docs].sort((a, b) =>
                (rank[a.status] ?? 9) - (rank[b.status] ?? 9) || (a.name || '').localeCompare(b.name || ''));
        },
        docStatusMeta(d) {
            return {
                indexed: { dot: 'bg-emerald-500', label: 'Indexed' },
                pending: { dot: 'bg-amber-500', label: 'Pending' },
                error:   { dot: 'bg-rose-500', label: 'Error' },
            }[d.status] || { dot: 'bg-gray-300', label: 'Skipped' };
        },
        docMeta(d) {
            if (d.status === 'indexed' && d.indexed_at) return this.fmtDate(d.indexed_at);
            return this.docStatusMeta(d).label;
        },
        fmtDate(iso) {
            try { return new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short' }); }
            catch (e) { return ''; }
        },

        // Turn a raw citation into a plain-language, colour-coded tag. Hides the
        // internal tool names (query_issues, plan_vs_actual…) users shouldn't see.
        sourceMeta(c) {
            const type = c.source_type || '';
            if (type === 'tool') {
                const label = (c.source_label || '').replace('Live data: ', '');
                const friendly = {
                    query_issues:    'Issue records',
                    query_claims:    'Claim records',
                    plan_vs_actual:  'Progress data',
                    project_summary: 'Project summary',
                    query_sentiment: 'Team morale',
                }[label] || 'Live project data';
                return { label: friendly, cls: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' };
            }
            if (type === 'document') {
                return { label: c.source_label, cls: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' };
            }
            if (type === 'weekly_update' || type === 'week_note' || type === 'comment') {
                return { label: c.source_label, cls: 'bg-purple-50 text-purple-700 border-purple-200', dot: 'bg-purple-500' };
            }
            // issues / wbs / ledger / claims / project profile = live data
            return { label: c.source_label, cls: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' };
        },

        ask(q) { this.input = q; this.send(); },

        async send() {
            const q = this.input.trim();
            if (!q || this.loading) return;
            this.messages.push({ role: 'user', content: q, citations: [] });
            this.input = '';
            this.loading = true;
            this.scroll();
            try {
                const res = await fetch(cfg.askUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.token, 'Accept': 'application/json' },
                    body: JSON.stringify({ question: q, model: this.model }),
                });
                if (!res.ok) {
                    const e = await res.json().catch(() => ({}));
                    this.messages.push({ role: 'assistant', content: e.error || 'Something went wrong.', grounded: false, citations: [] });
                } else {
                    const d = await res.json();
                    this.messages.push({ role: 'assistant', content: d.answer, citations: d.citations || [], grounded: d.grounded, model: d.model });
                }
            } catch (e) {
                this.messages.push({ role: 'assistant', content: 'Network error — please try again.', grounded: false, citations: [] });
            }
            this.loading = false;
            this.scroll();
        },

        async sync() {
            if (this.syncing) return;
            this.syncing = true;
            try {
                await fetch(cfg.syncUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': cfg.token, 'Accept': 'application/json' } });
                await this.pollStatus();
            } catch (e) {}
            setTimeout(() => { this.syncing = false; this.pollStatus(); }, 4000);
        },

        async pollStatus() {
            if (!cfg.statusUrl) return;
            try {
                const r = await fetch(cfg.statusUrl, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.docs = d.documents || [];
            } catch (e) {}
        },

        scroll() {
            this.$nextTick(() => { const el = this.$refs.scroll; if (el) el.scrollTop = el.scrollHeight; });
        },
    };
}
</script>
@endpush
