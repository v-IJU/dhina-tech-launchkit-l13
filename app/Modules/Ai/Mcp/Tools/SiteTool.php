<?php

declare(strict_types=1);

namespace App\Modules\Ai\Mcp\Tools;

use App\Models\User;
use App\Modules\Ai\Support\AiUsage;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Throwable;

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
        $user = $user instanceof User ? $user : null;
        $usage = app(AiUsage::class);
        $log = function (string $status, ?string $message = null, int $ms = 0) use ($usage, $user): void {
            $usage->record('mcp', $user, [
                'tools' => [$this->name()], 'status' => $status, 'message' => $message, 'duration_ms' => $ms,
                'provider' => Str::limit((string) request()->userAgent(), 40, ''), // which AI client
            ]);
            $usage->checkAlerts($user, $status, $this->name());
        };

        if ($user === null || ! $this->allows($user)) {
            $log('denied', 'No permission');

            return Response::error('You do not have permission to use this tool.');
        }
        if ($this->writes && ! $this->tokenCanWrite($user)) {
            $log('denied', 'Read-only token');

            return Response::error('This token is read-only. Create a token with full access to make changes.');
        }

        $started = hrtime(true);
        try {
            $result = $this->run($request->all(), $user);
        } catch (Throwable $e) {
            $log('error', $e->getMessage(), (int) ((hrtime(true) - $started) / 1_000_000));
            throw $e;
        }
        $log('ok', null, (int) ((hrtime(true) - $started) / 1_000_000));

        return Response::structured($result);
    }

    /*
    | Personal data. With Settings > AI > "Hide personal data" on, tools send masked values
    | to the AI: r***@gmail.com, ******3210, "Ravi K." Use these for anything personal you return.
    */
    protected function masking(): bool
    {
        return (bool) setting('ai_mask_personal_data', false);
    }

    protected function email(?string $email): ?string
    {
        if ($email === null || ! $this->masking() || ! str_contains($email, '@')) {
            return $email;
        }
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    protected function phone(?string $phone): ?string
    {
        if ($phone === null || ! $this->masking()) {
            return $phone;
        }
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return $digits === '' ? null : str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    protected function person(?string $name): ?string
    {
        if ($name === null || ! $this->masking()) {
            return $name;
        }
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = array_shift($parts) ?? '';

        return trim($first.' '.implode(' ', array_map(fn ($p) => mb_substr($p, 0, 1).'.', $parts)));
    }

    /** Free text (messages): hides email addresses and phone numbers inside it. */
    protected function text(?string $text): ?string
    {
        if ($text === null || ! $this->masking()) {
            return $text;
        }
        $text = preg_replace_callback('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', fn ($m) => (string) $this->email($m[0]), $text) ?? $text;

        return preg_replace_callback('/\+?\d[\d\s().-]{7,}\d/', fn ($m) => (string) $this->phone($m[0]), $text) ?? $text;
    }

    private function tokenCanWrite(User $user): bool
    {
        // Signed in by token (MCP over HTTP): check its abilities. Signed in by session (tests, panel): allowed.
        return $user->currentAccessToken() === null || $user->tokenCan('write');
    }
}
