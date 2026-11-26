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

Each user creates a token on **My profile → API tokens** with **For: AI client (MCP)** (*Read only* = the AI can look but not create drafts). AI client tokens only work on `/mcp` and normal API tokens don't work there. **Admin → AI & MCP** shows the address and ready-to-paste config with the right URL:

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

### Claude Code: things that trip people up

- **Scope.** `claude mcp add` without `-s` saves the server for the *exact folder* you ran it in (local scope). The VS Code extension opened on another folder won't see it. Use `-s user` to have it everywhere:
  ```bash
  claude mcp add -s user --transport http my-site http://localhost:8090/mcp --header "Authorization: Bearer <token>"
  ```
  Then VS Code: Ctrl+Shift+P → *Developer: Reload Window*. Check with `claude mcp list` or `/mcp` in Claude Code (`my-site ✓ connected · 4 tools`).
- **Inside the project folder Claude may skip MCP.** It can read your code and run `php artisan tinker`, so it answers without calling `/mcp` and nothing is logged. Ask explicitly ("Use the my-site MCP tool site-overview, don't run commands") or test from a folder outside the project. Your clients don't have your code, so for them everything goes through `/mcp`.
- **One token per site.** A token from `localhost:8090` doesn't work on `laravelnews.localhost:8080` and the other way round. The URL and the token must come from the same site / tenant.
- **Login:** Claude Code must be logged in with your Claude.ai subscription (`/login` → Claude.ai Subscription), not an API key with no credit.

## 3. Tools included

| Tool | Needs | What it does |
| --- | --- | --- |
| `site-overview` | signed in | Site name, URLs, counts the user may see (users, pages, posts, customers, unread enquiries) |
| `search-content` | `pages.view` or `blog.view` | Search pages and blog posts |
| `list-enquiries` | `inbox.view` | Latest contact-form messages, optionally unread only |
| `create-blog-draft` | `blog.create`, full-access token | New blog post as a **draft**, returns the edit link |

## 4. Usage, costs and limits

**Admin → AI & MCP** shows this month's usage on this site (SaaS: this tenant):

| Shown | Meaning |
| --- | --- |
| Assistant tokens (of limit) | Tokens the in-panel assistant used. **These are billed on your AI provider account** (Groq, OpenAI…) |
| Questions / MCP tool calls | How often each was used |
| Usage per person | Questions, MCP calls and tokens per user (visible to people with `users.view`) |
| Recent AI activity | Who, assistant or AI client, which tools, tokens, OK / error / denied / limit reached |

MCP calls show no tokens: Claude, ChatGPT or Cursor run on the person's own AI plan, so they cost you nothing. They're logged so you can see who used which tool.

**Limits** (the assistant stops until the 1st of next month and says so):
- **Settings → AI → Monthly token limit**: any site, empty = no limit.
- **SaaS:** the plan limit **AI tokens / month** (SaaS → Plans; Enterprise = 1,000,000), applied when the tenant uses **your** key from `.env`. A tenant that enters its own API key pays itself, and only its own Settings limit applies. The console's tenant page shows its AI tokens next to users and storage (measured nightly by `saas:collect-usage`).

Your provider's dashboard (e.g. console.groq.com → Usage) still shows the bill per API key; the panel adds *who* used it. Rows are kept for a year (`model:prune`, nightly).

In your own code, log AI calls the same way:

```php
app(\App\Modules\Ai\Support\AiUsage::class)->record('assistant', $user, [
    'provider' => 'groq', 'model' => $response->meta->model,
    'input_tokens' => $response->usage->inputTokens, 'output_tokens' => $response->usage->outputTokens,
]);
```

## 5. Security

What protects the data:

| Layer | What it does |
| --- | --- |
| Tools only | The AI can only call the tools you wrote; there is no "run SQL" or file access |
| Separate token type | AI client tokens (ability `mcp`) work only on `/mcp`; API tokens don't work on `/mcp`. A leaked app token can't be used by an AI, and the reverse |
| Expiry | AI client tokens always expire: **Settings → AI → AI client tokens expire after** (default 90 days) |
| Permissions | Every tool checks the user's permission and that the module is on (SaaS: in the plan) |
| Read-only | Read-only tokens can't use tools that change data |
| Drafts only | Tools never publish, send, pay or delete; a person approves |
| Hide personal data | **Settings → AI → Hide personal data from AI**: emails, phones and surnames are masked (`r***@gmail.com`, `******3210`, `Ravi K.`), also inside message text |
| Alerts | **Settings → AI → Security alerts**: email + bell to admins (super admin, `settings.edit`) when a call is refused, or a user makes more than N tool calls in an hour (default 200). Max one alert per user and kind per hour |
| Audit | Every call is in Recent AI activity (who, tool, client, result) |
| Off switch | Settings → AI → *Allow AI clients (MCP)*; or revoke one token |

Remaining risks to keep in mind:
- **Prompt injection**: text stored in your data (a contact message, a customer note) can contain instructions to the AI. Keep writing tools few and draft-only, and give read-only tokens where possible.
- **Data goes to the AI company** (Anthropic, OpenAI, Groq…). For sensitive data, turn on *Hide personal data*, return counts instead of records, or use Ollama on your own server for the assistant.

Checklist for every new tool: `$permission` and `$module` set · `$writes = true` if it changes anything · input cast and validated · no raw SQL, file paths or shell from AI input · results limited · personal fields through `$this->email()`, `$this->phone()`, `$this->person()`, `$this->text()` · a test that a user without permission is refused.

## 6. Add your own tool (the main way to extend)

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

## 7. More with the Laravel AI SDK

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

## 8. Files

```
app/Modules/Ai/
├── module.json                    permissions ai.view / ai.mcp / ai.assistant, menu
├── Mcp/SiteServer.php             the MCP server + list of tools (TOOLS)
├── Mcp/Tools/SiteTool.php         base class: module + permission + read-only checks
├── Mcp/Tools/*.php                the four tools
├── Agents/SiteAssistant.php       in-panel assistant (Laravel AI SDK)
├── Agents/AgentTool.php           lets the assistant use the MCP tools
├── Support/AiSettings.php         provider / model / key from Settings > AI or .env
├── Support/AiUsage.php            usage log + monthly limits
├── Models/AiUsageLog.php          one row per question / MCP tool call (table ai_usage_logs)
├── Middleware/McpAccess.php       /mcp: switched on, staff, active, AI client token, ai.mcp
├── Notifications/AiSecurityAlert.php  email + bell on refused / unusual AI activity
├── Providers/AiServiceProvider.php  registers POST /mcp
└── Controllers, Routes, Views     Admin > AI & MCP page
config/ai.php                      providers and default models (Laravel AI SDK)
tests/Feature/AiModuleTest.php     tools, permissions, tokens, assistant (faked)
```

## 9. Test checklist (after install or update)

Settings → AI: MCP enabled on, provider + model + API key, Mask personal data on, Alerts on. Then:

| # | Do | Expected |
|---|---|---|
| 1 | Admin → AI & MCP, ask "Give me a site overview" | Answer with counts; Usage card and Recent activity show the question and tokens |
| 2 | Ask "Show the latest enquiries" | Emails and phones masked (`r***@gmail.com`, `******3210`) |
| 3 | Ask "Write a short blog draft about Docker" | New **Draft** post in Blog, entry in Activity log |
| 4 | Settings → AI → Monthly token limit `100`, ask again | "Monthly AI limit reached", no AI call. Set back to `0` |
| 5 | Profile → API tokens → For: AI client, Read + write | Token with **AI** badge and an expiry date |
| 6 | `curl` tools/call `site-overview` with that token (below) | JSON result; Recent activity shows an **MCP** row |
| 7 | Same call with no token / a normal API token | 401 / 403 |
| 8 | AI token on the REST API (`/api/v1/me`) | 403 |
| 9 | Read-only AI token calls `create-blog-draft` | Refused, no post, **AI security alert** email (Mailpit) + bell |
| 10 | Alert after calls per hour `2`, make 3 calls | One "Unusual AI client activity" email. Set back to `200` |
| 11 | Claude Code connected (`-s user`), "Use the my-site tool site-overview" | `my-site - site-overview (MCP)` in the reply, row in Recent activity |
| 12 | Settings → AI → MCP enabled off | `/mcp` refused |

```bash
curl -s -X POST http://localhost:8090/mcp -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"site-overview","arguments":{}}}'

# count logged MCP calls on this site
docker compose exec app php artisan tinker --execute="echo App\Modules\Ai\Models\AiUsageLog::where('channel','mcp')->count();"
```

Revoking one person: remove `ai.*` from their role and give it back as extra permissions (Users → key icon) to the others, or delete their AI tokens in Users. Automated tests: `tests/Feature/AiModuleTest.php`.

