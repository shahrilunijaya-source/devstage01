@extends('layouts.ursb')
@section('title', 'Platform settings')
@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Platform settings</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">API keys and feature flags (admin only). Secrets are write-only — they are never shown back.</p>
        </div>
        <a class="btn-secondary" href="{{ route('admin.acl.index') }}">Access control</a>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-[10px] bg-red-50 border border-red-200 px-4 py-2.5 text-[13px] text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="mb-8 max-w-2xl">
        <h2 class="section-title mb-3">AI / RAG configuration</h2>
        <form class="card card-pad space-y-5" method="POST" action="{{ route('admin.settings.update') }}">
            @csrf

            <div>
                <label class="form-label flex items-center gap-2">
                    Anthropic API key
                    <span class="badge {{ $isSet['anthropic_api_key'] ? 'badge-teal' : 'badge-gray' }}">{{ $isSet['anthropic_api_key'] ? 'set' : 'not set' }}</span>
                </label>
                <input type="password" name="anthropic_api_key" autocomplete="new-password"
                       class="form-input" placeholder="{{ $isSet['anthropic_api_key'] ? 'leave blank to keep current' : 'sk-ant-…' }}">
                <p class="text-[12px] text-gray-400 mt-1">Powers LLM session pre-analysis (drafts findings &amp; requirements from evidence).</p>
            </div>

            <div>
                <label class="form-label flex items-center gap-2">
                    Voyage API key
                    <span class="badge {{ $isSet['voyage_api_key'] ? 'badge-teal' : 'badge-gray' }}">{{ $isSet['voyage_api_key'] ? 'set' : 'not set' }}</span>
                </label>
                <input type="password" name="voyage_api_key" autocomplete="new-password"
                       class="form-input" placeholder="{{ $isSet['voyage_api_key'] ? 'leave blank to keep current' : 'pa-…' }}">
                <p class="text-[12px] text-gray-400 mt-1">Embeddings for retrieval over indexed evidence (RAG).</p>
            </div>

            <div>
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="hidden" name="rag_enabled" value="0">
                    <input type="checkbox" name="rag_enabled" value="1" @checked($ragEnabled)
                           class="w-4 h-4 rounded border-gray-300 text-teal focus:ring-teal">
                    <span class="text-[13px] font-medium text-gray-800">Enable AI / RAG</span>
                </label>
                <p class="text-[12px] text-gray-400 mt-1 ml-6">
                    When enabled <em>and</em> both keys are set, session pre-analysis uses the LLM analyst;
                    otherwise it falls back to the deterministic offline analyst. Either way the workflow is identical.
                </p>
            </div>

            <div class="pt-1">
                <button class="btn-primary" type="submit">Save settings</button>
            </div>
        </form>
    </section>

    <section class="max-w-2xl">
        <h2 class="section-title mb-3">Current state</h2>
        <div class="card card-pad">
            <div class="flex items-center gap-2 text-[13px]">
                <span class="text-gray-500">LLM pre-analysis:</span>
                @if ($ragEnabled && $isSet['anthropic_api_key'])
                    <span class="badge badge-teal">active</span>
                @else
                    <span class="badge badge-gray">deterministic fallback</span>
                @endif
            </div>
            @unless ($ragEnabled && $isSet['anthropic_api_key'])
                <p class="text-[12px] text-gray-400 mt-2">Set the Anthropic key and tick “Enable AI / RAG” to switch on real LLM drafting.</p>
            @endunless
        </div>
    </section>
@endsection
