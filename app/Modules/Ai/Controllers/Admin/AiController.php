<?php

declare(strict_types=1);

namespace App\Modules\Ai\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Ai\Agents\SiteAssistant;
use App\Modules\Ai\Mcp\SiteServer;
use App\Modules\Ai\Mcp\Tools\SiteTool;
use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Ai\Support\AiSettings;
use App\Modules\Ai\Support\AiUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Throwable;

/**
 * Admin > AI & MCP: status, how to connect an AI client, the tools this user gets,
 * and a simple box to ask the in-panel assistant.
 */
class AiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:ai.view', only: ['index']),
            new Middleware('permission:ai.assistant', only: ['ask']),
        ];
    }

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $tools = collect(SiteServer::TOOLS)
            ->map(fn (string $class) => app($class))
            ->map(fn (SiteTool $tool) => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'writes' => $tool->writes(),
                'allowed' => $tool->allows($user),
            ]);

        return view('ai::admin.index', [
            'mcpUrl' => url('mcp'),
            'mcpEnabled' => (bool) setting('ai_mcp_enabled', false),
            'canMcp' => $user->can('ai.mcp'),
            'canAsk' => $user->can('ai.assistant'),
            'provider' => AiSettings::provider(),
            'providerName' => AiSettings::PROVIDERS[AiSettings::provider() ?? ''] ?? null,
            'assistantReady' => AiSettings::ready(),
            'tools' => $tools,
            'usage' => $this->usageSummary($user),
            'question' => session('ai.question'),
            'answer' => session('ai.answer'),
        ]);
    }

    /**
     * This month on this site / tenant. Everyone sees the totals and their own rows;
     * people who manage users (users.view) also see every user.
     *
     * @return array<string, mixed>
     */
    private function usageSummary(User $user): array
    {
        $usage = app(AiUsage::class);
        $all = $user->can('users.view');
        $month = AiUsageLog::query()->thisMonth();

        $perUser = (clone $month)
            ->when(! $all, fn ($q) => $q->where('user_id', $user->id))
            ->selectRaw("user_id, SUM(CASE WHEN channel = 'assistant' THEN 1 ELSE 0 END) as questions, SUM(CASE WHEN channel = 'mcp' THEN 1 ELSE 0 END) as mcp_calls, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens")
            ->groupBy('user_id')->orderByRaw('SUM(input_tokens + output_tokens) DESC')->limit(20)->toBase()->get();
        $names = User::query()->whereIn('id', $perUser->pluck('user_id')->filter())->pluck('name', 'id');

        return [
            'tokens' => $usage->monthTokens(),
            'limit' => $usage->monthlyLimit(),
            'questions' => (clone $month)->where('channel', 'assistant')->count(),
            'mcp_calls' => (clone $month)->where('channel', 'mcp')->count(),
            'all_users' => $all,
            'per_user' => $perUser->map(fn ($row) => [
                'name' => $names[$row->user_id] ?? 'Deleted user',
                'questions' => (int) $row->questions,
                'mcp_calls' => (int) $row->mcp_calls,
                'tokens' => (int) $row->input_tokens + (int) $row->output_tokens,
            ])->all(),
            'recent' => AiUsageLog::query()->with('user:id,name')
                ->when(! $all, fn ($q) => $q->where('user_id', $user->id))
                ->latest('id')->limit(15)->get(),
        ];
    }

    public function ask(Request $request): RedirectResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:2000']]);

        if (! AiSettings::ready()) {
            return back()->withErrors(['question' => 'Choose an AI provider and add its API key under Settings > AI first.']);
        }

        /** @var User $user */
        $user = $request->user();
        $usage = app(AiUsage::class);
        $base = ['provider' => AiSettings::provider(), 'model' => AiSettings::model()];

        if ($usage->limitReached()) {
            $usage->record('assistant', $user, $base + ['status' => 'limit', 'message' => $data['question']]);

            return back()->withInput()->withErrors(['question' => 'This month\'s AI limit ('.number_format((int) $usage->monthlyLimit()).' tokens) is used up. It resets on the 1st, or an admin can raise it under Settings > AI.']);
        }

        AiSettings::apply();
        $started = hrtime(true);

        try {
            $response = SiteAssistant::make(user: $user)
                ->prompt($data['question'], provider: AiSettings::provider(), model: AiSettings::model());
        } catch (Throwable $e) {
            report($e);
            $usage->record('assistant', $user, $base + [
                'status' => 'error', 'message' => $e->getMessage(), 'tools' => $usage->flushTools(),
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            ]);

            return back()->withInput()->withErrors(['question' => 'The AI provider did not answer: '.$e->getMessage()]);
        }

        $usage->record('assistant', $user, [
            'provider' => $response->meta->provider ?? $base['provider'],
            'model' => $response->meta->model ?? $base['model'],
            'input_tokens' => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
            'tools' => $usage->flushTools(),
            'message' => $data['question'],
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ]);

        return back()->with(['ai.question' => $data['question'], 'ai.answer' => $response->text]);
    }
}
