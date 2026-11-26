<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after auth:sanctum on every /api/v1 route:
 *  - the API can be switched off in Settings > Security
 *  - only staff accounts (App\Models\User) can use it
 *  - inactive / suspended users can't use their tokens
 *  - "read only" tokens can only make GET requests
 */
class ApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! setting('api_enabled', true)) {
            return response()->json(['message' => 'The API is turned off.'], 503);
        }

        $user = $request->user();

        // Staff API only: tokens of other models (e.g. a future Customer model) can't use these routes.
        if (! $user instanceof User) {
            return response()->json(['message' => 'This token cannot use the staff API.'], 403);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'This account is not active.'], 403);
        }

        // Tokens made for AI clients (MCP) only work on /mcp, never on the REST API.
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken && in_array('mcp', (array) $token->abilities, true)) {
            return response()->json(['message' => 'This token is for AI clients (MCP) only. Create an API token on My profile.'], 403);
        }

        if (! $request->isMethodSafe() && ! $user->tokenCan('write')) {
            return response()->json(['message' => 'This token is read-only.'], 403);
        }

        return $next($request);
    }
}
