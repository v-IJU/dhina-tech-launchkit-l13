@extends('layouts.admin')

@php
    $claudeConfig = json_encode(['mcpServers' => [\Illuminate\Support\Str::slug(setting('site_name', config('app.name'))) => [
        'command' => 'npx',
        'args' => ['-y', 'mcp-remote', $mcpUrl, '--header', 'Authorization: Bearer ${API_TOKEN}'],
        'env' => ['API_TOKEN' => 'paste-your-token-here'],
    ]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $cursorConfig = json_encode(['mcpServers' => [\Illuminate\Support\Str::slug(setting('site_name', config('app.name'))) => [
        'url' => $mcpUrl,
        'headers' => ['Authorization' => 'Bearer paste-your-token-here'],
    ]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
@endphp

@section('content')
    <x-admin.page-header title="AI & MCP" :breadcrumbs="['System', 'AI & MCP']">
        @can('settings.view')
            <a href="{{ route('admin.settings.edit', ['tab' => 'ai']) }}" class="btn btn-outline-secondary"><i class="bi bi-gear me-1"></i> AI settings</a>
        @endcan
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-8 d-flex flex-column gap-4">
            <x-admin.card title="Ask the assistant" subtitle="Uses the tools below with your permissions. Blog posts it writes are saved as drafts.">
                @if (! $canAsk)
                    <p class="text-secondary small mb-0">Your role doesn't have the <code>ai.assistant</code> permission.</p>
                @elseif (! $assistantReady)
                    <div class="alert alert-info mb-0 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Choose an AI provider and add its API key under <strong>Settings &rsaquo; AI</strong> (or in <code>.env</code>, e.g. <code>OPENAI_API_KEY</code>) to use the assistant.
                    </div>
                @else
                    <form method="POST" action="{{ route('admin.ai.ask') }}" class="d-flex flex-column gap-2" data-ai-ask>
                        @csrf
                        <textarea name="question" rows="3" class="form-control @error('question') is-invalid @enderror" placeholder="e.g. Summarise this week's unread enquiries, or: Write a draft blog post about our new opening hours" required maxlength="2000">{{ old('question', $question) }}</textarea>
                        @error('question')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-primary"><i class="bi bi-stars me-1"></i> Ask</button>
                            <span class="small text-secondary">{{ $providerName }}{{ setting('ai_model') ? ' · '.setting('ai_model') : '' }}</span>
                        </div>
                    </form>
                    @if ($answer)
                        <div class="border rounded-3 p-3 mt-3 bg-body-tertiary" style="white-space: pre-line">{{ $answer }}</div>
                    @endif
                @endif
            </x-admin.card>

            <x-admin.card title="Connect an AI client (MCP)" subtitle="Claude Desktop, Claude Code, ChatGPT, Cursor, VS Code… can work with this site through the Model Context Protocol.">
                @unless ($mcpEnabled)
                    <div class="alert alert-warning small"><i class="bi bi-plug me-1"></i> AI clients are <strong>off</strong>. Turn on <em>Allow AI clients (MCP)</em> under Settings &rsaquo; AI.</div>
                @endunless
                @unless ($canMcp)
                    <div class="alert alert-secondary small">Your role doesn't have the <code>ai.mcp</code> permission, so your token can't connect.</div>
                @endunless

                <ol class="small ps-3 mb-3">
                    <li>Create an API token on <a href="{{ route('admin.profile.edit') }}">My profile</a> &rsaquo; API tokens. <em>Read only</em> = the AI can look but not create drafts.</li>
                    <li>Add the server to your AI client with the address and token below.</li>
                    <li>Ask it things like <em>"How many unread enquiries do we have?"</em> or <em>"Draft a blog post about…"</em>.</li>
                </ol>

                <label class="form-label small fw-semibold">MCP server address</label>
                <div class="input-group mb-3">
                    <input class="form-control font-monospace" value="{{ $mcpUrl }}" readonly id="mcp-url">
                    <button class="btn btn-outline-secondary" type="button" data-copy="{{ $mcpUrl }}"><i class="bi bi-clipboard"></i></button>
                </div>

                <div class="small fw-semibold mb-1">Cursor / VS Code / clients with HTTP support</div>
                <pre class="bg-body-tertiary border rounded-3 p-3 small mb-3"><code>{{ $cursorConfig }}</code></pre>

                <div class="small fw-semibold mb-1">Claude Desktop (<code>claude_desktop_config.json</code>, needs Node.js)</div>
                <pre class="bg-body-tertiary border rounded-3 p-3 small mb-3"><code>{{ $claudeConfig }}</code></pre>

                <div class="small fw-semibold mb-1">Claude Code</div>
                <pre class="bg-body-tertiary border rounded-3 p-3 small mb-0"><code>claude mcp add --transport http {{ \Illuminate\Support\Str::slug(setting('site_name', config('app.name'))) }} {{ $mcpUrl }} --header "Authorization: Bearer paste-your-token-here"</code></pre>
            </x-admin.card>
        </div>

        <div class="col-xl-4 d-flex flex-column gap-4">
            <x-admin.card title="Status">
                <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                    <li><i class="bi {{ $mcpEnabled ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-secondary' }} me-1"></i> AI clients (MCP) {{ $mcpEnabled ? 'on' : 'off' }}</li>
                    <li><i class="bi {{ $assistantReady ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-secondary' }} me-1"></i> Assistant {{ $assistantReady ? 'ready: '.$providerName : 'needs a provider and key' }}</li>
                </ul>
            </x-admin.card>

            <x-admin.card title="Tools" subtitle="What an AI can do here, with your permissions." flush>
                @foreach ($tools as $tool)
                    <div class="px-4 py-3 border-top {{ $tool['allowed'] ? '' : 'opacity-50' }}">
                        <div class="d-flex align-items-center gap-2">
                            <code class="small">{{ $tool['name'] }}</code>
                            @if ($tool['writes'])<span class="badge badge-soft-warning">changes data</span>@endif
                            @unless ($tool['allowed'])<span class="badge badge-soft-secondary ms-auto">not for you</span>@endunless
                        </div>
                        <div class="small text-secondary mt-1">{{ $tool['description'] }}</div>
                    </div>
                @endforeach
            </x-admin.card>
        </div>
    </div>
@endsection
