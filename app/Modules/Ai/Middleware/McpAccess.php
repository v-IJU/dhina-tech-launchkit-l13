<?php

declare(strict_types=1);

namespace App\Modules\Ai\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * /mcp: switched on in Settings > AI, staff accounts only, active, an "AI client" token
 * (ability "mcp", made on My profile), and the "ai.mcp" permission.
 * (MCP always uses POST, so read-only tokens are checked per tool instead of here.)
 */
class McpAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $deny = fn (string $message, int $status) => response()->json(['jsonrpc' => '2.0', 'error' => ['code' => -32001, 'message' => $message]], $status);

        if (! setting('ai_mcp_enabled', false)) {
            return $deny('AI clients (MCP) are turned off. Turn them on under Settings > AI.', 503);
        }

        $user = $request->user();
        if (! $user instanceof User || $user->status !== 'active') {
            return $deny('This token cannot use the MCP server.', 403);
        }
        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken || ! in_array('mcp', (array) $token->abilities, true)) {
            return $deny('Use an "AI client (MCP)" token: My profile > API tokens > For: AI client. Normal API tokens don\'t work here.', 403);
        }
        if (! $user->can('ai.mcp')) {
            return $deny('Your role is missing the "ai.mcp" permission.', 403);
        }

        return $next($request);
    }
}
