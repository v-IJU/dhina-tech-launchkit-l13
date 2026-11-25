# AI & MCP

The **AI & MCP** module (`app/Modules/Ai`) gives every project two AI features that share one set of tools:

| Feature | What it is | Built on |
| --- | --- | --- |
| **MCP server** at `/mcp` | Claude Desktop, Claude Code, ChatGPT, Cursor, VS Code… connect to the site and work with its data | [laravel/mcp](https://laravel.com/docs/mcp) |
| **Assistant** in Admin → AI & MCP | Staff ask questions or give tasks inside the panel | [Laravel AI SDK](https://laravel.com/docs/ai-sdk) (`laravel/ai`) |

Both run **as the signed-in user**: a tool only appears when its module is on and the user has its permission. Tools that change data refuse read-only tokens. Blog posts the AI writes are always drafts.

Everything is **off by default**.

---

## 1. Switch it on

1. **Settings → AI**
   - *Allow AI clients (MCP)*: on
   - *Assistant AI provider*: e.g. Anthropic or OpenAI, *Model* (empty = default), *API key* (stored encrypted; empty = the key from `.env`)
2. **Roles**: tick `ai.view` (see the page), `ai.mcp` (connect AI clients), `ai.assistant` (use the assistant). Super admins have all.
3. SaaS mode: the module is a plan module. Tick **AI & MCP** on a plan (Enterprise has it by default). Each tenant sets its own provider and key under its Settings → AI; without one it falls back to the key in `.env` (you pay).

## 2. Connect an AI client

Each user creates a token on **My profile → API tokens** (*Read only* = the AI can look but not create drafts). **Admin → AI & MCP** shows the address and ready-to-paste config with the right URL:

```json
// Cursor (.cursor/mcp.json), VS Code, clients with HTTP support
{ "mcpServers": { "my-site": { "url": "https://yoursite.com/mcp", "headers": { "Authorization": "Bearer 12|abc..." } } } }
```

```bash
# Claude Code
claude mcp add --transport http my-site https://yoursite.com/mcp --header "Authorization: Bearer 12|abc..."
```

Claude Desktop (`claude_desktop_config.json`) uses the `mcp-remote` bridge, shown on the page.

SaaS: each tenant connects to its own address, `https://acme.yourapp.com/mcp`, with a token from acme's panel. It only sees acme's data.

Test without an AI client:

```bash
docker compose exec app php artisan mcp:inspector mcp      # browser UI; add header Authorization: Bearer <token>
curl -s http://localhost:8080/mcp -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

## 3. Tools included

| Tool | Needs | What it does |
| --- | --- | --- |
| `site-overview` | signed in | Site name, URLs, counts the user may see (users, pages, posts, customers, unread enquiries) |
| `search-content` | `pages.view` or `blog.view` | Search pages and blog posts |
| `list-enquiries` | `inbox.view` | Latest contact-form messages, optionally unread only |
| `create-blog-draft` | `blog.create`, full-access token | New blog post as a **draft**, returns the edit link |

## 4. Add your own tool (the main way to extend)

One class = available in MCP clients **and** the assistant.

```php
// app/Modules/Products/Ai/LowStockTool.php  (any folder; keep it in its module)
namespace App\Modules\Products\Ai;

use App\Models\User;
use App\Modules\Ai\Mcp\Tools\SiteTool;
use App\Modules\Products\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Products whose stock is at or below a threshold.')]
class LowStockTool extends SiteTool
{
    protected string $name = 'low-stock-products';
    protected ?string $module = 'products';          // hidden when the module is off / not in the plan
    protected ?string $permission = 'products.view'; // hidden without this permission
    protected bool $writes = false;                   // true = refuses read-only tokens

    public function schema(JsonSchema $schema): array
    {
        return ['threshold' => $schema->integer()->description('Stock level, default 5.')];
    }

    public function run(array $input, User $user): array
    {
        return ['products' => Product::where('stock', '<=', (int) ($input['threshold'] ?? 5))
            ->get(['id', 'name', 'sku', 'stock'])->toArray()];
    }
}
```

Then add it to `SiteServer::TOOLS` in `app/Modules/Ai/Mcp/SiteServer.php`. Done: it shows up in the tools list on the AI page, in MCP clients and in the assistant.

Rules for tools:
- Return plain arrays. Keep them small: limit rows (`->limit(20)`) and long text (`Str::limit`).
- Anything that changes data: `$writes = true`, validate input yourself, prefer **drafts / pending** states that a person approves, and log it with `activity()`.
- Never return secrets (passwords, tokens, API keys, full card numbers).

Test it:

```php
SiteServer::actingAs($user)->tool(LowStockTool::class, ['threshold' => 3])->assertOk()->assertSee('sku');
```

## 5. More with the Laravel AI SDK

The assistant is `app/Modules/Ai/Agents/SiteAssistant.php`. Build other AI features the same way, anywhere in the app:

```php
use function Laravel\Ai\agent;

// One-off: summary, translation, SEO text…
$text = agent(instructions: 'Write a 155-character meta description.')
    ->prompt($post->content, provider: 'anthropic')->text;
```

```php
// Your own agent class: php artisan make:agent ProductCopywriter
SiteAssistant::make(user: $user)->prompt('Summarise unread enquiries')->text;
```

Before calling an agent from your own code, run `AiSettings::apply()` so the key from Settings → AI is used. Ideas that fit the starter: meta description / excerpt buttons on the blog form, reply drafts in the Inbox, enquiry classification, translation of pages. In tests, fake every call: `SiteAssistant::fake(['answer'])`, then `SiteAssistant::assertPrompted('…')`.

Long answers or many users: use `->queue(...)` or `->stream(...)` from the AI SDK, and Reverb (already set up) to push the answer to the browser.

## 6. Files

```
app/Modules/Ai/
├── module.json                    permissions ai.view / ai.mcp / ai.assistant, menu
├── Mcp/SiteServer.php             the MCP server + list of tools (TOOLS)
├── Mcp/Tools/SiteTool.php         base class: module + permission + read-only checks
├── Mcp/Tools/*.php                the four tools
├── Agents/SiteAssistant.php       in-panel assistant (Laravel AI SDK)
├── Agents/AgentTool.php           lets the assistant use the MCP tools
├── Support/AiSettings.php         provider / model / key from Settings > AI or .env
├── Middleware/McpAccess.php       /mcp: switched on, staff, active, ai.mcp
├── Providers/AiServiceProvider.php  registers POST /mcp
└── Controllers, Routes, Views     Admin > AI & MCP page
config/ai.php                      providers and default models (Laravel AI SDK)
tests/Feature/AiModuleTest.php     tools, permissions, tokens, assistant (faked)
```
