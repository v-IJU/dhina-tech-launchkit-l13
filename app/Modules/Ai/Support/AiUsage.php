<?php

declare(strict_types=1);

namespace App\Modules\Ai\Support;

use App\Core\Tenancy\Tenancy;
use App\Models\User;
use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Ai\Notifications\AiSecurityAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records and limits AI usage (Admin > AI & MCP > Usage).
 *
 *   assistant: tokens per question, paid on your AI provider account (Groq, OpenAI…)
 *   mcp:       every tool call from Claude / ChatGPT / Cursor (their plan pays the tokens)
 *
 * Monthly token limit for the assistant = the lower of:
 *   - Settings > AI > Monthly token limit (any site)
 *   - SaaS: the tenant's plan limit "AI tokens / month", when the tenant uses YOUR key from .env
 */
class AiUsage
{
    /** @var list<string> tools the assistant used during this request */
    private array $tools = [];

    public function toolUsed(string $name): void
    {
        $this->tools[] = $name;
    }

    /**
     * @return list<string>
     */
    public function flushTools(): array
    {
        [$tools, $this->tools] = [$this->tools, []];

        return $tools;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(string $channel, ?User $user, array $data = []): void
    {
        try {
            AiUsageLog::query()->create([
                'user_id' => $user?->id,
                'channel' => $channel,
                'provider' => $data['provider'] ?? null,
                'model' => isset($data['model']) ? Str::limit((string) $data['model'], 100, '') : null,
                'input_tokens' => (int) ($data['input_tokens'] ?? 0),
                'output_tokens' => (int) ($data['output_tokens'] ?? 0),
                'tools' => ($data['tools'] ?? []) ?: null,
                'status' => $data['status'] ?? 'ok',
                'message' => isset($data['message']) ? Str::limit((string) $data['message'], 480) : null,
                'duration_ms' => (int) ($data['duration_ms'] ?? 0),
            ]);
        } catch (Throwable $e) {
            report($e); // never break an AI answer because the log failed
        }
    }

    /**
     * After an MCP call: alert the site's managers about denied calls or unusual volume
     * (Settings > AI > Security alerts). At most one alert per user, kind and hour.
     */
    public function checkAlerts(?User $user, string $status, string $tool): void
    {
        if ($user === null || ! setting('ai_alerts', true)) {
            return;
        }

        try {
            if ($status === 'denied') {
                $this->alert($user, 'denied', 'AI client call refused', "{$user->name} ({$user->email}) tried to use the AI tool \"{$tool}\" and was refused (no permission or read-only token).");
            }

            $max = (int) setting('ai_alert_hourly_calls', 200);
            if ($max > 0) {
                $calls = AiUsageLog::query()->where('user_id', $user->id)->where('channel', 'mcp')
                    ->where('created_at', '>=', now()->subHour())->count();
                if ($calls > $max) {
                    $this->alert($user, 'volume', 'Unusual AI client activity', "{$user->name}'s token made {$calls} AI tool calls in the last hour (alert level: {$max}). A script or a stolen token?");
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function alert(User $user, string $kind, string $subject, string $body): void
    {
        $context = Tenancy::tenant()?->getTenantKey() ?? 'central';
        if (! Cache::add("ai-alert:{$context}:{$kind}:{$user->id}", true, now()->addHour())) {
            return; // already alerted this hour
        }

        $recipients = User::query()->where('status', 'active')->get()
            ->filter(fn (User $u) => $u->isSuperAdmin() || $u->can('settings.edit'));

        Notification::send($recipients, new AiSecurityAlert($subject, $body, route('admin.ai.index')));
    }

    /** Assistant tokens used this month on this site / tenant. */
    public function monthTokens(): int
    {
        return (int) AiUsageLog::query()->thisMonth()->where('channel', 'assistant')
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) as total')->value('total');
    }

    /** Effective monthly limit, null = no limit. */
    public function monthlyLimit(): ?int
    {
        $limits = [];

        if (filled(setting('ai_monthly_token_limit'))) {
            $limits[] = (int) setting('ai_monthly_token_limit');
        }

        // SaaS: a tenant on the platform's key is limited by its plan; with its own key it pays itself.
        if (Tenancy::inTenant() && blank(setting('ai_api_key')) && ($plan = Tenancy::tenant()?->limit('ai_tokens')) !== null) {
            $limits[] = (int) $plan;
        }

        return $limits === [] ? null : min($limits);
    }

    public function limitReached(): bool
    {
        $limit = $this->monthlyLimit();

        return $limit !== null && $this->monthTokens() >= $limit;
    }
}
