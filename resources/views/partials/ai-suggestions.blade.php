{{--
    AI refinement panel (spec §10). Required: $object, $suggestions, $canEdit,
    $aiEnabled. Suggestions are proposals — accept / accept-with-edits / reject.
--}}
<div class="mt-6">
    <div class="flex items-center justify-between mb-2">
        <h2 class="section-title">AI refinement</h2>
        <span class="text-[12px] text-gray-400">AI advises — you decide. Nothing is applied without your acceptance.</span>
    </div>

    @if ($canEdit)
        <div class="card card-pad mb-4">
            <div class="flex flex-wrap gap-2">
                @php
                    $actions = [
                        'improve' => ['Improve wording', 'Tighter, testable phrasing — you approve the change'],
                        'challenge' => ['Challenge this', 'A sceptical review: necessity, feasibility, ambiguity'],
                        'generate_ac' => ['Generate acceptance criteria', 'Testable criteria, minted as AC objects on accept'],
                        'find_missing' => ['Find missing information', 'Gaps and the questions to ask'],
                        'check_objective' => ['Check against objective', 'Alignment with the approved project objective'],
                    ];
                @endphp
                @foreach ($actions as $key => [$label, $hint])
                    <form method="POST" action="{{ route('objects.refine', $object) }}">
                        @csrf
                        <input type="hidden" name="action" value="{{ $key }}">
                        <button type="submit" class="btn-secondary" title="{{ $hint }}"
                            @if(! $aiEnabled && $key !== 'check_objective') disabled @endif>{{ $label }}</button>
                    </form>
                @endforeach
            </div>
            @unless ($aiEnabled)
                <p class="text-[12px] text-gray-400 mt-2">AI actions need API keys (admin → Settings). "Check against objective" degrades gracefully.</p>
            @endunless
        </div>
    @endif

    @forelse ($suggestions as $suggestion)
        @php $p = $suggestion->payload; @endphp
        <div class="card card-pad mb-3 {{ $suggestion->status === 'proposed' ? 'border-l-4 border-teal' : 'opacity-75' }}">
            <div class="flex items-start justify-between gap-3">
                <p class="text-[13px] text-gray-500">
                    <span class="font-semibold text-gray-900">#{{ $suggestion->id }} · {{ str_replace('_', ' ', $suggestion->action) }}</span>
                    · {{ $suggestion->model ?? 'deterministic' }} · prompt {{ $suggestion->prompt_version }}
                    · by {{ $suggestion->creator?->name }} {{ $suggestion->created_at->diffForHumans() }}
                </p>
                <div class="flex items-center gap-1.5">
                    @if (! empty($p['confidence']))<span class="badge badge-gray">{{ $p['confidence'] }} confidence</span>@endif
                    <span class="badge {{ $suggestion->status === 'proposed' ? 'badge-flag' : ($suggestion->status === 'rejected' ? 'badge-danger' : 'badge-teal') }}">{{ str_replace('_', ' ', $suggestion->status) }}</span>
                </div>
            </div>

            @if (! empty($p['classification']))
                <p class="mt-2"><span class="badge {{ in_array($p['classification'], ['aligned', 'potential_improvement']) ? 'badge-teal' : ($p['classification'] === 'insufficient_information' ? 'badge-gray' : 'badge-danger') }}">{{ str_replace('_', ' ', $p['classification']) }}</span></p>
            @endif

            @if (! empty($p['proposed_title']) || ! empty($p['proposed_body']))
                <div class="mt-3 grid md:grid-cols-2 gap-3 text-[13px]">
                    <div class="bg-paper rounded p-3">
                        <p class="text-[11px] uppercase tracking-wide text-gray-400 mb-1">Current</p>
                        <p class="font-medium text-gray-900">{{ $object->title }}</p>
                        <p class="text-gray-600 mt-1 whitespace-pre-line">{{ $object->body }}</p>
                    </div>
                    <div class="bg-teal/5 border border-teal/20 rounded p-3">
                        <p class="text-[11px] uppercase tracking-wide text-teal mb-1">Proposed</p>
                        <p class="font-medium text-gray-900">{{ $p['proposed_title'] ?? $object->title }}</p>
                        <p class="text-gray-600 mt-1 whitespace-pre-line">{{ $p['proposed_body'] ?? $object->body }}</p>
                    </div>
                </div>
            @endif

            @if (! empty($p['rationale']))
                <p class="text-[13px] text-gray-700 mt-3"><span class="font-medium">Reasoning:</span> {{ $p['rationale'] }}</p>
            @endif

            @foreach (['issues' => 'Issues', 'gaps' => 'Missing information', 'questions' => 'Questions to ask', 'benefits' => 'Benefits', 'risks' => 'Risks'] as $key => $label)
                @if (! empty($p[$key]))
                    <div class="mt-2 text-[13px]">
                        <p class="font-medium text-gray-800">{{ $label }}:</p>
                        <ul class="list-disc ml-5 text-gray-600">
                            @foreach ($p[$key] as $item)<li>{{ $item }}</li>@endforeach
                        </ul>
                    </div>
                @endif
            @endforeach

            @if (! empty($p['criteria']))
                <div class="mt-2 text-[13px]">
                    <p class="font-medium text-gray-800">Proposed acceptance criteria (minted as AC objects on accept):</p>
                    <ul class="list-disc ml-5 text-gray-600">
                        @foreach ($p['criteria'] as $c)<li><span class="font-medium">{{ $c['title'] ?? '' }}</span> — {{ $c['body'] ?? '' }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($p['sources']))
                <p class="text-[12px] text-gray-400 mt-2">Context used: {{ implode(', ', $p['sources']) }}</p>
            @endif

            @if ($suggestion->status === 'proposed' && $canEdit)
                <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap gap-2" x-data="{ editing: false, rejecting: false }">
                    <form method="POST" action="{{ route('suggestions.decide', $suggestion) }}">@csrf
                        <input type="hidden" name="decision" value="accept">
                        <button type="submit" class="btn-primary">Accept</button>
                    </form>
                    @if ($suggestion->action === 'improve')
                        <button type="button" class="btn-secondary" @click="editing = !editing">Accept &amp; edit…</button>
                    @endif
                    <button type="button" class="btn-secondary" @click="rejecting = !rejecting">Reject…</button>

                    <form method="POST" action="{{ route('suggestions.decide', $suggestion) }}" class="w-full grid gap-2" x-show="editing" x-cloak>
                        @csrf
                        <input type="hidden" name="decision" value="accept_edit">
                        <input type="text" name="title" maxlength="255" class="form-input" value="{{ $p['proposed_title'] ?? $object->title }}">
                        <textarea name="body" rows="3" class="form-textarea">{{ $p['proposed_body'] ?? $object->body }}</textarea>
                        <div><button type="submit" class="btn-primary">Apply my version</button></div>
                    </form>

                    <form method="POST" action="{{ route('suggestions.decide', $suggestion) }}" class="w-full flex gap-2" x-show="rejecting" x-cloak>
                        @csrf
                        <input type="hidden" name="decision" value="reject">
                        <input type="text" name="note" maxlength="500" class="form-input flex-1" placeholder="Why is this rejected… (recorded)">
                        <button type="submit" class="btn-danger">Reject</button>
                    </form>
                </div>
            @elseif ($suggestion->decided_at)
                <p class="text-[12px] text-gray-400 mt-2">
                    {{ str_replace('_', ' ', $suggestion->status) }} by {{ $suggestion->decider?->name }} {{ $suggestion->decided_at->diffForHumans() }}
                    @if ($suggestion->decision_note) — {{ $suggestion->decision_note }}@endif
                </p>
            @endif
        </div>
    @empty
        <div class="card card-pad"><p class="text-sm text-gray-400 italic">No AI suggestions yet.</p></div>
    @endforelse
</div>
