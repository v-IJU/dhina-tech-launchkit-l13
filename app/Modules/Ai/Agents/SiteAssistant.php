<?php

declare(strict_types=1);

namespace App\Modules\Ai\Agents;

use App\Models\User;
use App\Modules\Ai\Mcp\SiteServer;
use App\Modules\Ai\Mcp\Tools\SiteTool;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The in-panel assistant (Admin > AI & MCP), built on the Laravel AI SDK.
 * Same tools as the MCP server, limited to what this user may do.
 *
 *   SiteAssistant::make(user: $user)->prompt('How many unread enquiries do we have?')->text
 *
 * Tests: SiteAssistant::fake(['answer…']) (no AI provider is called).
 */
class SiteAssistant implements Agent, HasTools
{
    use Promptable;

    public function __construct(public User $user) {}

    public function instructions(): Stringable|string
    {
        return 'You are the assistant inside the admin panel of "'.setting('site_name', config('app.name')).'". '
            .'You help '.$this->user->name.' with the website and its data. Use the tools to look things up instead of guessing. '
            .'Blog posts you create are drafts; say that a person must review and publish them. '
            .'Answer briefly, in plain language, in the language the user writes in.';
    }

    /**
     * @return iterable<int, AgentTool>
     */
    public function tools(): iterable
    {
        return collect(SiteServer::TOOLS)
            ->map(fn (string $class) => app($class))
            ->filter(fn (SiteTool $tool) => $tool->allows($this->user))
            ->map(fn (SiteTool $tool) => new AgentTool($tool, $this->user))
            ->values()
            ->all();
    }
}
