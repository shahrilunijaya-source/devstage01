{{--
    Shared discussions panel (spec §12). Required: $discussions (visibility-
    filtered collection), $discussableKind (project|stage|session|baseline|object),
    $discussableId, $canEdit (bool). Comment/open allowed for any viewer of the
    page; resolve/convert/blocking are edit-gated server-side too.
--}}
<div class="mt-6">
    <div class="flex items-center justify-between mb-2">
        <h2 class="section-title">Discussions</h2>
        <span class="text-[12px] text-gray-400">{{ $discussions->where('status', 'open')->count() }} open · {{ $discussions->count() }} total</span>
    </div>

    <div class="card card-pad mb-4" x-data="{ open: false }">
        <button type="button" class="btn-secondary" x-show="!open" @click="open = true">Start a discussion…</button>
        <form method="POST" action="{{ route('discussions.store') }}" x-show="open" x-cloak>
            @csrf
            <input type="hidden" name="discussable_kind" value="{{ $discussableKind }}">
            <input type="hidden" name="discussable_id" value="{{ $discussableId }}">
            <div class="grid gap-3">
                <div>
                    <label class="form-label">Topic</label>
                    <input type="text" name="title" maxlength="255" required class="form-input" placeholder="What needs discussing…">
                </div>
                <div>
                    <label class="form-label">Comment</label>
                    <textarea name="body" rows="3" required maxlength="10000" class="form-textarea" placeholder="Question, challenge, missing information…"></textarea>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    @unless (auth()->user()->isClient())
                        <label class="text-[13px] text-gray-600 flex items-center gap-1.5">
                            <select name="visibility" class="form-select">
                                <option value="internal">Internal note</option>
                                <option value="client">Client-visible</option>
                            </select>
                        </label>
                    @endunless
                    @if ($canEdit)
                        <label class="text-[13px] text-gray-600 flex items-center gap-1.5">
                            <input type="checkbox" name="blocking" value="1"> Blocks the stage gate until resolved
                        </label>
                    @endif
                    <button type="submit" class="btn-primary">Open discussion</button>
                </div>
            </div>
        </form>
    </div>

    @forelse ($discussions as $discussion)
        <div class="card card-pad mb-3 {{ $discussion->status === 'open' ? ($discussion->blocking ? 'border-l-4 border-flag' : '') : 'opacity-70' }}">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[14px] font-semibold text-gray-900">{{ $discussion->title }}</p>
                    <p class="text-[12px] text-gray-400 mt-0.5">
                        by {{ $discussion->opener?->name }} · {{ $discussion->created_at->diffForHumans() }}
                        @if ($discussion->assignee) · assigned to {{ $discussion->assignee->name }}@endif
                        @if ($discussion->due_at) · due {{ $discussion->due_at->format('d/m/Y') }}@endif
                    </p>
                </div>
                <div class="flex items-center gap-1.5">
                    @if ($discussion->visibility === 'client')
                        <span class="badge badge-teal">client-visible</span>
                    @else
                        <span class="badge badge-gray">internal</span>
                    @endif
                    @if ($discussion->blocking && $discussion->status === 'open')
                        <span class="badge badge-danger">blocking</span>
                    @endif
                    <span class="badge {{ $discussion->status === 'open' ? 'badge-flag' : 'badge-pine' }}">{{ $discussion->status }}</span>
                </div>
            </div>

            <div class="mt-3 space-y-2">
                @foreach ($discussion->comments as $comment)
                    <div class="text-[13px] bg-paper rounded px-3 py-2">
                        <span class="font-medium text-gray-800">{{ $comment->user?->name }}:</span>
                        <span class="text-gray-700 whitespace-pre-line">{{ $comment->body }}</span>
                        @if ($comment->converted_ref)
                            <span class="badge badge-pine ml-1">→ {{ $comment->converted_ref }}</span>
                        @elseif ($canEdit && $discussion->status === 'open')
                            <form method="POST" action="{{ route('discussions.convert', [$discussion, $comment]) }}" class="inline-flex items-center gap-1 ml-2">
                                @csrf
                                <select name="target" class="text-[12px] border border-gray-200 rounded px-1 py-0.5">
                                    <option value="">Convert to…</option>
                                    <option value="requirement">Requirement</option>
                                    <option value="risk">Risk</option>
                                    <option value="decision">Decision</option>
                                    <option value="issue">Issue</option>
                                    <option value="change_request">Change request</option>
                                </select>
                                <button type="submit" class="text-[12px] text-teal hover:underline">Go</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-3 flex flex-wrap items-start gap-2">
                @if ($discussion->status === 'open')
                    <form method="POST" action="{{ route('discussions.comment', $discussion) }}" class="flex-1 min-w-[240px] flex gap-2">
                        @csrf
                        <input type="text" name="body" maxlength="10000" required class="form-input flex-1" placeholder="Reply…">
                        <button type="submit" class="btn-secondary">Reply</button>
                    </form>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('discussions.resolve', $discussion) }}">@csrf
                            <button type="submit" class="btn-secondary">Resolve</button>
                        </form>
                    @endif
                @elseif ($canEdit)
                    <p class="text-[12px] text-gray-400">
                        Resolved by {{ $discussion->resolver?->name }} {{ $discussion->resolved_at?->diffForHumans() }}.
                    </p>
                    <form method="POST" action="{{ route('discussions.reopen', $discussion) }}">@csrf
                        <button type="submit" class="text-[12px] text-teal hover:underline">Reopen</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="card card-pad"><p class="text-sm text-gray-400 italic">No discussions yet.</p></div>
    @endforelse
</div>
