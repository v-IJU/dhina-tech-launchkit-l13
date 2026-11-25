<?php

declare(strict_types=1);

namespace App\Modules\Ai\Support;

/**
 * Which AI provider the in-panel assistant uses: Settings > AI (per site / per tenant),
 * falling back to .env (OPENAI_API_KEY, ANTHROPIC_API_KEY, GEMINI_API_KEY…, see config/ai.php).
 */
final class AiSettings
{
    /** Providers offered in Settings > AI (all supported by laravel/ai). */
    public const PROVIDERS = [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic (Claude)',
        'gemini' => 'Google Gemini',
        'groq' => 'Groq',
        'mistral' => 'Mistral',
        'deepseek' => 'DeepSeek',
        'openrouter' => 'OpenRouter',
        'ollama' => 'Ollama (self-hosted)',
    ];

    public static function provider(): ?string
    {
        $provider = (string) setting('ai_provider', '');

        return array_key_exists($provider, self::PROVIDERS) ? $provider : null;
    }

    public static function model(): ?string
    {
        return setting('ai_model') ?: null;
    }

    /** Has a provider and a key (from Settings or .env)? Ollama needs no key. */
    public static function ready(): bool
    {
        $provider = self::provider();

        return $provider !== null && ($provider === 'ollama' || filled(self::key($provider)));
    }

    /** Use the key from Settings for this request (it wins over .env). */
    public static function apply(): void
    {
        $provider = self::provider();
        if ($provider !== null && filled(setting('ai_api_key'))) {
            config(["ai.providers.{$provider}.key" => setting('ai_api_key')]);
        }
    }

    private static function key(string $provider): ?string
    {
        return setting('ai_api_key') ?: config("ai.providers.{$provider}.key");
    }
}
