<?php

declare(strict_types=1);

namespace App\Modules\Ai\Mcp\Tools;

use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Base for every tool AI clients can call. One class serves both:
 *   - the MCP server (Claude Desktop, ChatGPT, Cursor… connect to /mcp)
 *   - the in-panel assistant (Laravel AI SDK, see Agents/SiteAssistant)
 *
 * A tool only shows up for a user when its module is on (SaaS: in the tenant's plan) and the user
 * has its permission. Tools that change data refuse read-only API tokens.
 *
 * New tool: extend this class, implement run(), add it to SiteServer::TOOLS.
 */
abstract class SiteTool extends Tool
{
    /** Module that must be on, e.g. "blog". Null = always. */
    protected ?string $module = null;

    /** Permission the user needs, e.g. "blog.view". Null = any signed-in staff user. */
    protected ?string $permission = null;

    /** True when the tool creates or changes data. */
    protected bool $writes = false;

    /**
     * The tool's work. Return plain arrays/scalars: they reach the AI client as structured JSON.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    abstract public function run(array $input, User $user): array;

    public function allows(?User $user): bool
    {
        return $user instanceof User
            && ($this->module === null || module_enabled($this->module))
            && ($this->permission === null || $user->can($this->permission));
    }

    public function writes(): bool
    {
        return $this->writes;
    }

    /** MCP: hidden from users who can't use it. */
    public function shouldRegister(Request $request): bool
    {
        $user = $request->user();

        return $this->allows($user instanceof User ? $user : null);
    }

    /** MCP: called by AI clients. */
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        if (! $user instanceof User || ! $this->allows($user)) {
            return Response::error('You do not have permission to use this tool.');
        }
        if ($this->writes && ! $this->tokenCanWrite($user)) {
            return Response::error('This token is read-only. Create a token with full access to make changes.');
        }

        return Response::structured($this->run($request->all(), $user));
    }

    private function tokenCanWrite(User $user): bool
    {
        // Signed in by token (MCP over HTTP): check its abilities. Signed in by session (tests, panel): allowed.
        return $user->currentAccessToken() === null || $user->tokenCan('write');
    }
}
