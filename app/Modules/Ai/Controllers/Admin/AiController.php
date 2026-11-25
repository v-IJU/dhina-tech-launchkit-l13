<?php

declare(strict_types=1);

namespace App\Modules\Ai\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Ai\Agents\SiteAssistant;
use App\Modules\Ai\Mcp\SiteServer;
use App\Modules\Ai\Mcp\Tools\SiteTool;
use App\Modules\Ai\Support\AiSettings;
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
            'question' => session('ai.question'),
            'answer' => session('ai.answer'),
        ]);
    }

    public function ask(Request $request): RedirectResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:2000']]);

        if (! AiSettings::ready()) {
            return back()->withErrors(['question' => 'Choose an AI provider and add its API key under Settings > AI first.']);
        }

        AiSettings::apply();

        try {
            $answer = SiteAssistant::make(user: $request->user())
                ->prompt($data['question'], provider: AiSettings::provider(), model: AiSettings::model())
                ->text;
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['question' => 'The AI provider did not answer: '.$e->getMessage()]);
        }

        return back()->with(['ai.question' => $data['question'], 'ai.answer' => $answer]);
    }
}
