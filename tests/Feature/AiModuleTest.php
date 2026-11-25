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
use App\Modules\Blog\Models\Post;
use App\Modules\Inbox\Models\ContactMessage;
use Illuminate\Testing\TestResponse;
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
        $full = $admin->createToken('claude', ['*'])->plainTextToken;
        $read = $admin->createToken('claude-ro', ['read'])->plainTextToken;

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
        $this->rpc($nobody->createToken('t')->plainTextToken, 'tools/list')->assertForbidden(); // editors don't get ai.mcp
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
}
