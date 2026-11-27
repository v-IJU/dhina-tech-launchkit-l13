<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use App\Modules\Pages\Models\Page;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ContentTest extends TestCase
{
    private function category(): Category
    {
        return Category::query()->create(['name' => 'News']);
    }

    public function test_publish_post_and_see_it_on_the_website(): void
    {
        $admin = $this->superAdmin();
        $category = $this->category();

        $this->actingAs($admin)->post(route('admin.blog.posts.store'), [
            'title' => 'Hello World', 'content' => '<p>Body</p><script>alert(1)</script>', 'category_id' => $category->id,
            'author_id' => $admin->id, 'status' => 'published', 'tags' => 'laravel, news', 'is_featured' => '0',
        ])->assertRedirect();

        $post = Post::query()->where('slug', 'hello-world')->firstOrFail();
        $this->assertTrue($post->isLive());
        $this->assertSame(['laravel', 'news'], $post->tags);
        $this->assertStringNotContainsString('<script>', $post->content, 'HTML is purified');

        auth()->logout();
        $this->get(route('blog.show', $post))->assertOk()->assertSee('Hello World');
        $this->get(route('blog.index'))->assertSee('Hello World');
    }

    public function test_drafts_and_future_posts_are_hidden_from_visitors(): void
    {
        $category = $this->category();
        $draft = Post::query()->create(['title' => 'Secret draft', 'content' => 'x', 'category_id' => $category->id, 'status' => 'draft']);
        $later = Post::query()->create(['title' => 'Future post', 'content' => 'x', 'category_id' => $category->id, 'status' => 'scheduled', 'published_at' => now()->addDay()]);

        $this->get(route('blog.show', $draft))->assertNotFound();
        $this->get(route('blog.show', $later))->assertNotFound();
        $this->get(route('blog.index'))->assertDontSee('Secret draft')->assertDontSee('Future post');

        $this->travel(2)->days();
        $this->artisan('blog:publish-scheduled')->assertSuccessful();
        $this->assertSame('published', $later->fresh()->status);
        $this->get(route('blog.show', $later))->assertOk();
    }

    public function test_user_without_publish_permission_saves_drafts_only(): void
    {
        $role = Role::create(['name' => 'writer', 'guard_name' => 'web']);
        $role->givePermissionTo(['blog.view', 'blog.create', 'blog.edit']);
        $writer = $this->userWithRole('writer');

        $this->actingAs($writer)->post(route('admin.blog.posts.store'), [
            'title' => 'My post', 'content' => '<p>Hi</p>', 'category_id' => $this->category()->id,
            'author_id' => $writer->id, 'status' => 'published',
        ])->assertRedirect();

        $this->assertSame('draft', Post::query()->where('title', 'My post')->value('status'));
    }

    public function test_page_builder_saves_blocks_and_renders_them(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.pages.store'), [
            'title' => 'Careers', 'slug' => '', 'status' => 'published', 'is_home' => '0',
            'blocks' => [
                'abc' => ['type' => 'hero', 'data' => ['heading' => 'Join our team', 'unknown' => 'dropped']],
                'xyz' => ['type' => 'text', 'data' => ['heading' => 'Open roles', 'body' => '<p>None yet</p><script>x</script>']],
            ],
        ])->assertRedirect();

        $page = Page::query()->where('slug', 'careers')->firstOrFail();
        $this->assertSame(['hero', 'text'], array_column($page->blocks, 'type'));
        $this->assertArrayNotHasKey('unknown', $page->blocks[0]['data']);
        $this->assertStringNotContainsString('<script>', $page->blocks[1]['data']['body']);

        auth()->logout();
        $this->get('/careers')->assertOk()->assertSee('Join our team')->assertSee('Open roles');
    }

    public function test_unknown_block_type_is_rejected(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.pages.store'), ['title' => 'X', 'status' => 'draft', 'blocks' => [['type' => 'evil']]])
            ->assertSessionHasErrors('blocks.0.type');
    }

    public function test_draft_page_is_only_visible_to_staff_and_home_page_works(): void
    {
        Page::query()->create(['title' => 'Hidden', 'slug' => 'hidden', 'status' => 'draft', 'blocks' => []]);
        Page::query()->create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'is_home' => true, 'blocks' => [['type' => 'hero', 'data' => ['heading' => 'Welcome home']]]]);

        $this->get('/hidden')->assertNotFound();
        $this->get('/')->assertOk()->assertSee('Welcome home');
        $this->actingAs($this->superAdmin())->get('/hidden')->assertOk()->assertSee('Draft preview');
    }

    public function test_reserved_slugs_and_admin_path_are_not_pages(): void
    {
        $this->actingAs($this->superAdmin());
        $this->post(route('admin.pages.store'), ['title' => 'Admin', 'slug' => config('starter.admin_path'), 'status' => 'published'])->assertSessionHasErrors('slug');
    }

    public function test_sitemap_and_robots(): void
    {
        Cache::flush();
        Page::query()->create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'blocks' => []]);

        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee(url('/about'), false);
        $this->app['env'] = 'production';   // only the live site invites search engines (StagingTest covers the rest)
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap:');
    }
}
