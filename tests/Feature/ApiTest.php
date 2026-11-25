<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Settings\SettingsRepository;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use App\Modules\Faqs\Models\Faq;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    public function test_token_sign_in_me_and_revoke(): void
    {
        $user = $this->userWithRole('editor');

        $response = $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'Secret#123', 'device_name' => 'Phone'])
            ->assertCreated()->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'email', 'roles', 'permissions']]);
        $token = $response->json('token');

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', $user->email);
        $this->assertDatabaseHas('login_events', ['user_id' => $user->id, 'event' => 'login']);

        $this->withToken($token)->deleteJson('/api/v1/auth/token')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_wrong_password_and_inactive_users_get_no_token(): void
    {
        $user = $this->userWithRole('editor');
        $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'nope', 'device_name' => 'x'])->assertUnprocessable();

        $user->update(['status' => 'suspended']);
        $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'Secret#123', 'device_name' => 'x'])->assertUnprocessable();
    }

    public function test_requests_without_token_get_401_json(): void
    {
        $this->get('/api/v1/faqs')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_list_uses_the_same_filters_as_the_admin_tables(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);
        foreach (['Alpha', 'Beta', 'Gamma'] as $i => $q) {
            Faq::query()->create(['question' => $q, 'answer' => 'A', 'is_active' => $i !== 1]);
        }

        $this->getJson('/api/v1/faqs?status=1&sort=question&dir=desc&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.question', 'Gamma')
            ->assertJsonStructure(['data' => [['id', 'question', 'answer', 'is_active', 'created_at']], 'links', 'meta']);
    }

    public function test_crud_and_validation(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $id = $this->postJson('/api/v1/faqs', ['question' => 'From the app?', 'answer' => 'Yes', 'is_active' => true])
            ->assertCreated()->json('data.id');

        $this->postJson('/api/v1/faqs', ['answer' => 'No question'])->assertUnprocessable()->assertJsonValidationErrors('question');
        $this->putJson("/api/v1/faqs/{$id}", ['question' => 'Edited', 'answer' => 'Yes', 'is_active' => false])->assertOk()->assertJsonPath('data.question', 'Edited');
        $this->getJson("/api/v1/faqs/{$id}")->assertOk()->assertJsonPath('data.is_active', false);
        $this->deleteJson("/api/v1/faqs/{$id}")->assertOk();
        $this->assertSoftDeleted('faqs', ['id' => $id]);
    }

    public function test_permissions_apply_to_the_api(): void
    {
        $user = $this->userWithRole('editor'); // can view/edit FAQs but not delete, no users.*
        Sanctum::actingAs($user, ['*']);
        $faq = Faq::query()->create(['question' => 'Q', 'answer' => 'A']);

        $this->getJson('/api/v1/faqs')->assertOk();
        $this->deleteJson("/api/v1/faqs/{$faq->id}")->assertForbidden()->assertJson(['message' => 'You do not have permission to do this.']);
    }

    public function test_read_only_tokens_cannot_write(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['read']);

        $this->getJson('/api/v1/faqs')->assertOk();
        $this->postJson('/api/v1/faqs', ['question' => 'Q', 'answer' => 'A'])->assertForbidden()->assertJson(['message' => 'This token is read-only.']);
    }

    public function test_api_can_be_switched_off(): void
    {
        app(SettingsRepository::class)->set(['api_enabled' => false]);
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson('/api/v1/faqs')->assertStatus(503);
    }

    public function test_blog_posts_api(): void
    {
        $admin = $this->superAdmin();
        Sanctum::actingAs($admin, ['*']);
        $category = Category::query()->create(['name' => 'News']);

        $id = $this->postJson('/api/v1/blog/posts', [
            'title' => 'Hello from the app', 'content' => '<p>Hi</p><script>alert(1)</script>', 'category_id' => $category->id,
            'author_id' => $admin->id, 'status' => 'published', 'tags' => 'app,mobile',
        ])->assertCreated()->json('data.id');

        $this->assertStringNotContainsString('<script>', Post::query()->findOrFail($id)->content);
        $this->getJson('/api/v1/blog/posts?status=published')->assertOk()->assertJsonPath('data.0.category.name', 'News');
        $this->getJson("/api/v1/blog/posts/{$id}")->assertOk()->assertJsonPath('data.tags', ['app', 'mobile']);
        $this->getJson('/api/v1/blog/categories')->assertOk()->assertJsonPath('data.0.posts', 1);
    }

    public function test_profile_page_creates_and_revokes_tokens(): void
    {
        $user = $this->userWithRole('editor');
        $this->actingAs($user);

        $this->post(route('admin.profile.tokens.store'), ['token_name' => 'Script', 'token_access' => 'read', 'token_expires' => '30'])
            ->assertRedirect()->assertSessionHas('new_token');

        $token = $user->tokens()->firstOrFail();
        $this->assertSame(['read'], $token->abilities);
        $this->get(route('admin.profile.edit', ['tab' => 'security']))->assertSee('Script')->assertSee('Read only');

        $this->delete(route('admin.profile.tokens.destroy', $token->id))->assertRedirect();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_api_docs_are_only_for_admins(): void
    {
        $this->get('/docs/api.json')->assertForbidden();

        $this->actingAs($this->userWithRole('editor'));
        $this->get('/docs/api.json')->assertForbidden();

        $this->actingAs($this->superAdmin());
        $this->get('/docs/api')->assertOk();
        $this->getJson('/docs/api.json')->assertOk()->assertJsonPath('paths./faqs.get.parameters.0.name', 'q');
    }

    public function test_editor_app_flow_upload_photo_and_create_post(): void
    {
        Storage::fake('uploads');
        $editor = $this->userWithRole('editor');
        $token = $this->postJson('/api/v1/auth/token', ['email' => $editor->email, 'password' => 'Secret#123', 'device_name' => 'iPhone'])->json('token');
        $category = Category::query()->create(['name' => 'News']);

        $photo = $this->withToken($token)->post('/api/v1/media', ['file' => UploadedFile::fake()->image('cover.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.url');

        $id = $this->withToken($token)->postJson('/api/v1/blog/posts', [
            'title' => 'Written on my phone', 'content' => '<p>Hello</p>', 'category_id' => $category->id,
            'status' => 'published', 'featured_image' => $photo,
        ])->assertCreated()->assertJsonPath('data.author.id', $editor->id)->json('data.id');

        $this->withToken($token)->getJson('/api/v1/blog/posts?q=phone')->assertOk()->assertJsonPath('meta.total', 1);
        $this->withToken($token)->deleteJson("/api/v1/blog/posts/{$id}")->assertForbidden(); // editors can't delete
    }
}
