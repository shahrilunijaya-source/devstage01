{{-- Unified Feedback hub: Report a bug/idea + What's New, in one modal.
     Expects $latestRelease (nullable). Opened via $dispatch('open-modal','feedback-hub'). --}}
@php
    $latestRelease = $latestRelease ?? null;
    $hubSections = [
        'new' => ['label' => 'New', 'color' => 'text-green-700', 'dot' => 'bg-green-500'],
        'improved' => ['label' => 'Improved', 'color' => 'text-blue-700', 'dot' => 'bg-blue-500'],
        'fixed' => ['label' => 'Fixed', 'color' => 'text-amber-700', 'dot' => 'bg-amber-500'],
    ];
    $hubUnseen = $latestRelease && $latestRelease->version !== \Illuminate\Support\Arr::get(auth()->user()->getAttributes(), 'last_seen_version');
    // Reopen the modal after a failed submit so validation errors are visible.
    $hasFeedbackError = $errors->hasAny(['type', 'title', 'description', 'page_url'])
        || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'attachments'));
@endphp

<x-modal name="feedback-hub" maxWidth="lg" :show="$hasFeedbackError">
    <div x-data="{
            tab: 'report',
            files: [],
            max: 52428800,
            markSeen() {
                @if($latestRelease && \Illuminate\Support\Facades\Route::has('releases.seen'))
                fetch('{{ route('releases.seen') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ version: @js($latestRelease->version) }),
                }).catch(() => {});
                @endif
            }
         }">
        {{-- Header + tabs --}}
        <div class="px-6 pt-4 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <h3 class="text-[15px] font-semibold text-gray-900">Feedback &amp; Updates</h3>
                <button type="button" @click="$dispatch('close-modal', 'feedback-hub')" class="text-gray-400 hover:text-gray-600">
                    <x-heroicon-o-x-mark class="w-5 h-5"/>
                </button>
            </div>
            <div class="flex gap-4 mt-3 -mb-px">
                <button type="button" @click="tab = 'report'"
                        :class="tab === 'report' ? 'border-teal text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="pb-2.5 text-[13px] font-medium border-b-2 transition-colors">Report</button>
                <button type="button" @click="tab = 'whatsnew'; markSeen()"
                        :class="tab === 'whatsnew' ? 'border-teal text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="pb-2.5 text-[13px] font-medium border-b-2 transition-colors flex items-center gap-1.5">
                    What's New
                    @if($hubUnseen)<span class="w-1.5 h-1.5 rounded-full bg-teal"></span>@endif
                </button>
            </div>
        </div>

        {{-- Report tab --}}
        <div x-show="tab === 'report'">
            <form method="POST" action="{{ route('feedback.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="page_url" :value="window.location.href">
                <div class="px-6 py-5 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div>
                        <label class="form-label">Type *</label>
                        <select name="type" class="form-select" required>
                            <option value="bug" @selected(old('type') === 'bug')>🐞 Bug report</option>
                            <option value="feature" @selected(old('type') === 'feature')>💡 Feature request</option>
                        </select>
                        @error('type')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" value="{{ old('title') }}" class="form-input" required maxlength="255" placeholder="Short summary">
                        @error('title')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="4" class="form-textarea" placeholder="What happened / what you'd like. For bugs: steps, expected vs actual.">{{ old('description') }}</textarea>
                        @error('description')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">Attachments <span class="text-[11px] text-gray-400 font-normal">(optional)</span></label>
                        <input type="file" name="attachments[]" multiple
                               accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                               @change="files = [...$event.target.files]" class="form-input">
                        <p class="text-[11px] text-gray-400 mt-1">Up to 5 files, 50 MB each. Screenshots, recordings (mp4/mov/webm), PDF, Office docs.</p>
                        @error('attachments')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                        @error('attachments.0')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                        <ul class="mt-2 space-y-1" x-show="files.length">
                            <template x-for="f in files" :key="f.name + f.size">
                                <li class="flex items-center justify-between text-[12px] px-2.5 py-1.5 rounded-lg bg-gray-50">
                                    <span class="truncate text-gray-700" x-text="f.name"></span>
                                    <span class="ml-2 shrink-0" :class="f.size > max ? 'text-red-600 font-semibold' : 'text-gray-400'"
                                          x-text="(f.size/1048576).toFixed(1) + ' MB' + (f.size > max ? ' — too big' : '')"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <a href="{{ route('feedback.index') }}" class="text-teal text-[12px] font-medium hover:text-teal-700">My feedback →</a>
                        @if(\Illuminate\Support\Facades\Route::has('how-it-works'))
                        <a href="{{ route('how-it-works') }}" class="text-gray-500 text-[12px] font-medium hover:text-gray-700">How the system works →</a>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        @can('viewAny', \App\Models\FeedbackItem::class)
                        <a href="{{ route('admin.feedback.index') }}" class="btn-secondary">Triage</a>
                        @endcan
                        <button type="submit" class="btn-primary">Submit</button>
                    </div>
                </div>
            </form>
        </div>

        {{-- What's New tab --}}
        <div x-show="tab === 'whatsnew'" x-cloak>
            <div class="px-6 py-5 space-y-3 max-h-[60vh] overflow-y-auto">
                @if($latestRelease)
                    <div class="text-[13px] font-semibold text-gray-900">{{ $latestRelease->displayTitle() }}</div>
                    @foreach($hubSections as $key => $meta)
                        @php $lines = $latestRelease->notes[$key] ?? []; @endphp
                        @if(!empty($lines))
                        <div>
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <span class="w-1.5 h-1.5 rounded-full {{ $meta['dot'] }}"></span>
                                <span class="text-[11px] font-semibold uppercase tracking-wide {{ $meta['color'] }}">{{ $meta['label'] }}</span>
                            </div>
                            <ul class="space-y-1 pl-4">
                                @foreach($lines as $line)
                                <li class="text-[13px] text-gray-700 leading-relaxed list-disc">{{ $line }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    @endforeach
                @else
                    <div class="text-center py-8 text-gray-300 text-[13px]">No updates yet.</div>
                @endif
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                <a href="#" class="text-teal text-[12px] font-medium hover:text-teal-700">View all updates →</a>
            </div>
        </div>
    </div>
</x-modal>
