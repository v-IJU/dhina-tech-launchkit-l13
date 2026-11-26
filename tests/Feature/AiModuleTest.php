<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Settings\SettingsRepository;
use App\Models\User;
use App\Modules\Ai\Agents\SiteAssistant;
use App\Modules\Ai\Mcp\SiteServer;
use App\Modules\Ai\Mcp\Tools\CreateBlogDraftTool;
use App\Modules\Ai\Mcp\Tools\ListEnquiriesTool;
use App\Modules\Ai\Mcp\Tools\SearchContentTool;
use App\Modules\Ai\Mcp\Tools\SiteOverviewTool;
use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Ai\Notifications\AiSecurityAlert;
use App\Modules\Blog\Models\Post;
use App\Modules\Inbox\Models\ContactMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\TextResponse;
use Tests\TestCase;

/**
 * AI & MCP module: the MCP server at /mcp, its tools, permissions, read-only tokens,
 * and the in-panel assistant (Laravel AI SDK, faked).
 */
class AiModuleTest extends TestCase
{
    private function settings(array $values): void
    {
        app(SettingsRepository::class)->set($values);
    }

    private function rpc(string $token, string $method, array $params = [], int $id = 1): TestResponse
    {
        $this->app['auth']->forgetGuards(); // each call authenticates by its own token, like real clients

        return $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json, text/event-stream'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);
    }

    public function test_tools_return_site_data(): void
    {
        $admin = $this->superAdmin();
        ContactMessage::query()->create(['name' => 'Ravi', 'email' => 'ravi@example.com', 'subject' => 'Opening hours', 'message' => 'Are you open on Sunday?']);
        Post::query()->create(['title' => 'Sunday opening hours', 'content' => '<p>We are open.</p>', 'status' => 'published', 'published_at' => now()->subDay(), 'author_id' => $admin->id]);

        SiteServer::actingAs($admin)->tool(SiteOverviewTool::class)
            ->assertOk()->assertSee('enquiries_unread');

        SiteServer::actingAs($admin)->tool(SearchContentTool::class, ['query' => 'Sunday'])
            ->assertOk()->assertSee('Sunday opening hours');

        SiteServer::actingAs($admin)->tool(ListEnquiriesTool::class, ['unread_only' => true])
            ->assertOk()->assertSee('Are you open on Sunday?');

        SiteServer::actingAs($admin)->tool(CreateBlogDraftTool::class, ['title' => 'AI draft', 'content' => "First paragraph.\n\nSecond paragraph."])
            ->assertOk()->assertSee('edit_url');

        $draft = Post::query()->where('title', 'AI draft')->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertStringContainsString('<p>First paragraph.</p>', $draft->content);
        $this->assertStringContainsString('<p>Second paragraph.</p>', $draft->content);
    }

    public function test_tools_follow_permissions(): void
    {
        $writer = $this->userWithRole('editor');
        $writer->revokePermissionTo([]); // role only
        $reader = User::query()->create(['name' => 'Reader', 'first_name' => 'R', 'last_name' => 'D', 'username' => 'reader', 'email' => 'reader@example.com', 'password' => 'x', 'status' => 'active']);
        $reader->givePermissionTo(['ai.mcp', 'blog.view']);

        // Editor: has inbox.view and blog.create.
        SiteServer::actingAs($writer)->tool(ListEnquiriesTool::class)->assertOk();

        // Reader: only blog.view → no enquiries, no drafts.
        SiteServer::actingAs($reader)->tool(ListEnquiriesTool::class)->assertHasErrors();
        SiteServer::actingAs($reader)->tool(CreateBlogDraftTool::class, ['title' => 'x', 'content' => 'y'])->assertHasErrors();
        SiteServer::actingAs($reader)->tool(SearchContentTool::class, ['query' => 'x'])->assertOk();
        $this->assertSame(0, Post::query()->count());
    }

    public function test_mcp_endpoint_with_api_tokens(): void
    {
        $admin = $this->superAdmin();
        $full = $admin->createToken('claude', ['mcp', 'write'])->plainTextToken;
        $read = $admin->createToken('claude-ro', ['mcp', 'read'])->plainTextToken;

        // Off by default.
        $this->rpc($full, 'tools/list')->assertStatus(503);

        $this->settings(['ai_mcp_enabled' => true]);

        $this->rpc($full, 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']])
            ->assertOk()->assertJsonPath('result.serverInfo.name', 'LaunchKit Site');

        $tools = collect($this->rpc($full, 'tools/list')->assertOk()->json('result.tools'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['site-overview', 'search-content', 'list-enquiries', 'create-blog-draft'], $tools);

        // Read-only token: may look, may not write.
        $this->rpc($read, 'tools/call', ['name' => 'site-overview', 'arguments' => (object) []])
            ->assertOk()->assertJsonPath('result.isError', false);
        $this->rpc($read, 'tools/call', ['name' => 'create-blog-draft', 'arguments' => ['title' => 'x', 'content' => 'y']])
            ->assertOk()->assertJsonPath('result.isError', true);
        $this->assertSame(0, Post::query()->count());

        $this->rpc($full, 'tools/call', ['name' => 'create-blog-draft', 'arguments' => ['title' => 'From Claude', 'content' => 'Hello']])
            ->assertOk()->assertJsonPath('result.isError', false);
        $this->assertSame('draft', Post::query()->where('title', 'From Claude')->value('status'));

        // No token / no permission.
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
        $nobody = $this->userWithRole('editor');
        $this->rpc($nobody->createToken('t', ['mcp', 'read'])->plainTextToken, 'tools/list')->assertForbidden(); // editors don't get ai.mcp
    }

    public function test_admin_page_and_assistant(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'web');

        $this->get($this->admin('ai'))->assertOk()
            ->assertSee(url('mcp'))
            ->assertSee('create-blog-draft')
            ->assertSee('Choose an AI provider');
        $this->get($this->admin('settings?tab=ai'))->assertOk()->assertSee('Allow AI clients (MCP)');

        // Not configured → friendly error, no AI call.
        SiteAssistant::fake(['should not be used']);
        $this->post($this->admin('ai/ask'), ['question' => 'Hi'])->assertSessionHasErrors('question');
        SiteAssistant::assertNeverPrompted();

        // Configured → the agent answers.
        $this->settings(['ai_provider' => 'anthropic', 'ai_api_key' => 'sk-test']);
        SiteAssistant::fake(['You have 0 unread enquiries.']);

        $this->post($this->admin('ai/ask'), ['question' => 'How many unread enquiries?'])->assertRedirect();
        $this->get($this->admin('ai'))->assertSee('You have 0 unread enquiries.');
        SiteAssistant::assertPrompted('How many unread enquiries?');
        $this->assertSame('sk-test', config('ai.providers.anthropic.key'));

        // The assistant gets exactly the tools this user may use.
        $this->assertCount(4, iterator_to_array((new SiteAssistant($admin))->tools()));
        $reader = User::query()->create(['name' => 'R', 'first_name' => 'R', 'last_name' => 'R', 'username' => 'r', 'email' => 'r@example.com', 'password' => 'x', 'status' => 'active']);
        $reader->givePermissionTo(['blog.view']);
        $this->assertSame(['site-overview', 'search-content'], collect((new SiteAssistant($reader))->tools())->map->name()->all());
    }

    public function test_usage_is_logged_and_limited(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'web');
        $this->settings(['ai_provider' => 'groq', 'ai_api_key' => 'gsk-test', 'ai_mcp_enabled' => true]);

        // The assistant calls a tool, then answers: tokens + tool are logged.
        SiteAssistant::fake([
            new ToolCall('call_1', 'site-overview', []),
            new TextResponse('Your site has 1 user.', new TextUsage(1200, 300), new Meta('groq', 'openai/gpt-oss-120b')),
        ]);
        $this->post($this->admin('ai/ask'), ['question' => 'Overview please'])->assertRedirect()->assertSessionHasNoErrors();

        $log = AiUsageLog::query()->where('channel', 'assistant')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(1500, $log->totalTokens());
        $this->assertSame(['site-overview'], $log->tools);
        $this->assertSame('ok', $log->status);

        // MCP calls are logged too (no tokens: the client's own AI pays).
        $token = $admin->createToken('claude', ['mcp', 'write'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json, text/event-stream', 'User-Agent' => 'claude-code/2.1'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'site-overview', 'arguments' => (object) []]])->assertOk();
        $this->flushHeaders();
        $mcp = AiUsageLog::query()->where('channel', 'mcp')->firstOrFail();
        $this->assertSame(['site-overview'], $mcp->tools);
        $this->assertSame('claude-code/2.1', $mcp->provider);

        // The page shows the totals and the activity.
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin, 'web');
        $this->get($this->admin('ai'))->assertOk()->assertSee('1,500')->assertSee('Usage per person')->assertSee('AI client (MCP)');

        // Monthly limit: reached → refused, logged as "limit", no AI call.
        $this->settings(['ai_monthly_token_limit' => 1000]);
        SiteAssistant::fake(['should not be used']);
        $this->post($this->admin('ai/ask'), ['question' => 'Again'])->assertSessionHasErrors('question');
        SiteAssistant::assertNotPrompted('Again');
        $this->assertSame(1, AiUsageLog::query()->where('status', 'limit')->count());
    }

    public function test_ai_tokens_are_separate_from_api_tokens(): void
    {
        $admin = $this->superAdmin();
        $this->settings(['ai_mcp_enabled' => true, 'ai_token_days' => 30]);

        // Profile: "For: AI client" → abilities mcp+write, expiry capped at 30 days even when "never" is chosen.
        $this->actingAs($admin, 'web')->post($this->admin('profile/tokens'), [
            'token_name' => 'Claude Code', 'token_for' => 'mcp', 'token_access' => 'full', 'token_expires' => 'never',
        ])->assertRedirect()->assertSessionHas('new_token');
        $made = $admin->tokens()->where('name', 'Claude Code')->firstOrFail();
        $this->assertSame(['mcp', 'write'], $made->abilities);
        $this->assertTrue($made->expires_at->between(now()->addDays(29), now()->addDays(31)));
        $this->get($this->admin('profile?tab=security'))->assertSee('AI client (MCP)');

        $ai = $admin->createToken('ai', ['mcp', 'write'])->plainTextToken;
        $api = $admin->createToken('app', ['*'])->plainTextToken;

        // An AI token can't use the REST API…
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$ai])->getJson('/api/v1/me')->assertForbidden();
        // …and an API token can't use /mcp.
        $this->rpc($api, 'tools/list')->assertForbidden()->assertSee('AI client');
        $this->rpc($ai, 'tools/list')->assertOk();

        // Expired AI token → 401.
        $expired = $admin->createToken('old', ['mcp', 'read'], now()->subDay())->plainTextToken;
        $this->rpc($expired, 'tools/list')->assertUnauthorized();
    }

    public function test_personal_data_can_be_hidden(): void
    {
        $admin = $this->superAdmin();
        ContactMessage::query()->create(['name' => 'Ravi Kumar Shah', 'email' => 'ravi.kumar@gmail.com', 'phone' => '+91 98765 43210', 'subject' => 'Call me', 'message' => 'Reach me at ravi.kumar@gmail.com or 98765 43210 please.']);

        SiteServer::actingAs($admin)->tool(ListEnquiriesTool::class)->assertSee('ravi.kumar@gmail.com');

        $this->settings(['ai_mask_personal_data' => true]);
        SiteServer::actingAs($admin)->tool(ListEnquiriesTool::class)
            ->assertOk()
            ->assertSee('r***@gmail.com')
            ->assertSee('Ravi K. S.')
            ->assertSee('********3210')
            ->assertDontSee('ravi.kumar@gmail.com')
            ->assertDontSee('98765 43210');
    }

    public function test_security_alerts_for_denied_and_unusual_calls(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();
        $reader = User::query()->create(['name' => 'Reader', 'first_name' => 'R', 'last_name' => 'D', 'username' => 'reader', 'email' => 'reader@example.com', 'password' => 'x', 'status' => 'active']);
        $reader->givePermissionTo(['ai.mcp', 'blog.view', 'blog.create']);
        $this->settings(['ai_mcp_enabled' => true, 'ai_alert_hourly_calls' => 5]);
        $token = $reader->createToken('claude', ['mcp', 'read'])->plainTextToken;

        // Read-only token tries to write → refused → one alert to the admin (not repeated within the hour).
        foreach ([1, 2] as $i) {
            $this->rpc($token, 'tools/call', ['name' => 'site-overview', 'arguments' => (object) []], $i)->assertOk();
        }
        $refused = fn (int $id) => $this->rpc($token, 'tools/call', ['name' => 'create-blog-draft', 'arguments' => ['title' => 'x', 'content' => 'y']], $id)
            ->assertOk()->assertJsonPath('result.isError', true);
        $refused(10);
        $refused(11);

        Notification::assertSentToTimes($admin, AiSecurityAlert::class, 1);

        // Above 5 calls in an hour → volume alert.
        foreach ([3, 4] as $i) {
            $this->rpc($token, 'tools/call', ['name' => 'site-overview', 'arguments' => (object) []], $i)->assertOk();
        }
        Notification::assertSentTo($admin, AiSecurityAlert::class, fn ($n) => str_contains($n->subject, 'Unusual'));

        // Switched off → no alerts.
        Notification::fake();
        Cache::flush();
        $this->settings(['ai_alerts' => false]);
        $refused(12);
        Notification::assertNothingSent();
    }
}
