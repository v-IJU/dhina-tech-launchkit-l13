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
                    <li>Create a token on <a href="{{ route('admin.profile.edit', ['tab' => 'security']) }}">My profile</a> &rsaquo; API tokens with <strong>For: AI client (MCP)</strong>. <em>Read only</em> = the AI can look but not create drafts. (Normal API tokens don't work here, and AI tokens don't work on the API.)</li>
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

            @if ($usage['all_users'] && count($usage['per_user']))
                <x-admin.card title="Usage per person" subtitle="This month" flush>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Person</th><th class="text-end">Questions</th><th class="text-end">MCP calls</th><th class="text-end">Tokens</th></tr></thead>
                            <tbody>
                            @foreach ($usage['per_user'] as $row)
                                <tr><td>{{ $row['name'] }}</td><td class="text-end">{{ number_format($row['questions']) }}</td><td class="text-end">{{ number_format($row['mcp_calls']) }}</td><td class="text-end fw-semibold">{{ number_format($row['tokens']) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-admin.card>
            @endif

            <x-admin.card title="Recent AI activity" :subtitle="$usage['all_users'] ? 'Everyone' : 'Yours'" flush>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead><tr><th>When</th><th>Who</th><th>Where</th><th>Tools</th><th class="text-end">Tokens</th><th>Result</th></tr></thead>
                        <tbody>
                        @forelse ($usage['recent'] as $log)
                            <tr>
                                <td class="text-nowrap text-secondary" title="{{ $log->created_at }}">{{ $log->created_at->diffForHumans() }}</td>
                                <td>{{ $log->user?->name ?? '—' }}</td>
                                <td>{{ $log->channel === 'mcp' ? 'AI client (MCP)' : 'Assistant' }}<div class="text-secondary text-truncate" style="max-width: 160px">{{ $log->channel === 'mcp' ? $log->provider : $log->model }}</div></td>
                                <td>@foreach ($log->tools ?? [] as $tool)<code class="me-1">{{ $tool }}</code>@endforeach</td>
                                <td class="text-end">{{ $log->channel === 'assistant' ? number_format($log->totalTokens()) : '—' }}</td>
                                <td><span class="badge badge-soft-{{ ['ok' => 'success', 'error' => 'danger', 'denied' => 'warning', 'limit' => 'warning'][$log->status] ?? 'secondary' }}" @if ($log->message && $log->status !== 'ok') title="{{ $log->message }}" @endif>{{ ['ok' => 'OK', 'error' => 'Error', 'denied' => 'Denied', 'limit' => 'Limit reached'][$log->status] ?? $log->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-secondary">Nothing yet this month.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div class="col-xl-4 d-flex flex-column gap-4">
            <x-admin.card title="Status">
                <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                    <li><i class="bi {{ $mcpEnabled ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-secondary' }} me-1"></i> AI clients (MCP) {{ $mcpEnabled ? 'on' : 'off' }}</li>
                    <li><i class="bi {{ $assistantReady ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-secondary' }} me-1"></i> Assistant {{ $assistantReady ? 'ready: '.$providerName : 'needs a provider and key' }}</li>
                </ul>
            </x-admin.card>

            <x-admin.card title="Usage this month" :subtitle="$usage['all_users'] ? 'Everyone on this site' : 'You'">
                <div class="d-flex align-items-baseline gap-2">
                    <span class="fs-3 fw-bold">{{ number_format($usage['tokens']) }}</span>
                    <span class="text-secondary small">assistant tokens{{ $usage['limit'] !== null ? ' of '.number_format($usage['limit']) : '' }}</span>
                </div>
                @if ($usage['limit'])
                    @php $pct = min(100, $usage['tokens'] / max(1, $usage['limit']) * 100); @endphp
                    <div class="progress mt-1" style="height: 6px"><div class="progress-bar {{ $pct >= 100 ? 'bg-danger' : ($pct > 80 ? 'bg-warning' : '') }}" style="width: {{ $pct }}%"></div></div>
                @endif
                <div class="row g-2 small mt-2">
                    <div class="col-6"><div class="text-secondary">Questions</div><div class="fw-semibold">{{ number_format($usage['questions']) }}</div></div>
                    <div class="col-6"><div class="text-secondary">MCP tool calls</div><div class="fw-semibold">{{ number_format($usage['mcp_calls']) }}</div></div>
                </div>
                <p class="small text-secondary mt-2 mb-0">Assistant tokens are billed on your {{ $providerName ?? 'AI provider' }} account. MCP clients use their own AI plan.</p>
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
