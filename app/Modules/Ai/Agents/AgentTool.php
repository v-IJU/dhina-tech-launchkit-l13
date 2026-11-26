<?php

declare(strict_types=1);

namespace App\Modules\Ai\Agents;

use App\Models\User;
use App\Modules\Ai\Mcp\Tools\SiteTool;
use App\Modules\Ai\Support\AiUsage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Lets the Laravel AI SDK assistant use an MCP tool (Mcp/Tools/*) as its own tool,
 * so every tool is written once and works in both places.
 */
final class AgentTool implements Tool
{
    public function __construct(private readonly SiteTool $tool, private readonly User $user) {}

    public function name(): string
    {
        return $this->tool->name();
    }

    public function description(): string
    {
        return $this->tool->description();
    }

    public function handle(Request $request): string
    {
        if (! $this->tool->allows($this->user)) {
            return 'Permission denied.';
        }

        app(AiUsage::class)->toolUsed($this->tool->name());

        return (string) json_encode($this->tool->run($request->all(), $this->user), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->tool->schema($schema);
    }
}
