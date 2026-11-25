<?php

declare(strict_types=1);

namespace App\Modules\Ai\Mcp;

use App\Modules\Ai\Mcp\Tools\CreateBlogDraftTool;
use App\Modules\Ai\Mcp\Tools\ListEnquiriesTool;
use App\Modules\Ai\Mcp\Tools\SearchContentTool;
use App\Modules\Ai\Mcp\Tools\SiteOverviewTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

/**
 * The MCP server AI clients connect to: POST /mcp with "Authorization: Bearer <API token>".
 * SaaS mode: each tenant on its own address (https://acme.yourapp.com/mcp), with its own data.
 *
 * Add a tool: create a class in Mcp/Tools extending SiteTool, then list it in TOOLS.
 * It is then available to MCP clients AND to the in-panel assistant (Agents/SiteAssistant).
 */
#[Name('LaunchKit Site')]
#[Version('1.0.0')]
#[Instructions('Tools for this business\'s website and admin panel: an overview, searching pages and blog posts, reading contact enquiries, and creating blog drafts. Every tool runs with the signed-in user\'s permissions. Drafts are never published automatically; tell the user to review them in the admin panel.')]
class SiteServer extends Server
{
    /** @var list<class-string<Tools\SiteTool>> */
    public const TOOLS = [
        SiteOverviewTool::class,
        SearchContentTool::class,
        ListEnquiriesTool::class,
        CreateBlogDraftTool::class,
    ];

    /** @var array<int, class-string<Tool>> */
    protected array $tools = self::TOOLS;
}
