<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Menus\MenuItem;
use App\Core\Settings\SettingsRepository;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use App\Modules\Pages\Models\Page;
use Tests\TestCase;

class LanguagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsRepository::class)->set(['default_locale' => 'en', 'website_locales' => 'ta,ar']);
    }

    private function page(): Page
    {
        return Page::query()->create([
            'title' => 'About us', 'slug' => 'about-us', 'status' => 'published',
            'blocks' => [['type' => 'text', 'data' => ['heading' => 'Who we are', 'body' => '<p>English text</p>']]],
        ]);
    }

    public function test_switcher_hreflang_and_rtl(): void
    {
        $this->page();

        $this->get('/about-us')->assertOk()
            ->assertSee('data-lang-switcher', false)
            ->assertSee('hreflang="ta"', false)
            ->assertSee('lang="en" dir="ltr"', false);

        $this->get('/about-us?lang=ar')->assertOk()
            ->assertSee('lang="ar" dir="rtl"', false)
            ->assertSee('تواصل معنا')          // "Get in touch" from lang/ar.json
            ->assertCookie('locale', 'ar');
    }

    public function test_translated_page_is_shown_only_when_published(): void
    {
        $page = $this->page();
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.pages.translate', [$page, 'ta']))->assertRedirect();
        $translation = $page->translations()->firstOrFail();
        $this->assertSame('ta', $translation->locale);
        $this->assertSame('draft', $translation->status);

        $translation->update(['title' => 'எங்களைப் பற்றி', 'blocks' => [['type' => 'text', 'data' => ['heading' => 'நாங்கள் யார்', 'body' => '<p>தமிழ் உரை</p>']]]]);
        auth()->logout();

        $this->get('/about-us?lang=ta')->assertOk()->assertSee('Who we are');   // draft → original shown

        $translation->update(['status' => 'published']);
        $this->get('/about-us?lang=ta')->assertOk()->assertSee('நாங்கள் யார்')->assertDontSee('Who we are');
        $this->get('/about-us?lang=en')->assertOk()->assertSee('Who we are');
        $this->get('/about-us-ta')->assertNotFound(); // translations have no URL of their own
    }

    public function test_blog_post_translation_and_lists(): void
    {
        $admin = $this->superAdmin();
        $category = Category::query()->create(['name' => 'News']);
        $post = Post::query()->create([
            'title' => 'Hello world', 'slug' => 'hello-world', 'content' => '<p>EN</p>', 'category_id' => $category->id,
            'author_id' => $admin->id, 'status' => 'published', 'published_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)->post(route('admin.blog.posts.translate', [$post, 'ta']))->assertRedirect();
        $translation = $post->translations()->firstOrFail();
        $translation->update(['title' => 'வணக்கம் உலகம்', 'status' => 'published', 'published_at' => now()->subHour()]);

        // The admin list shows the original once, with language badges.
        $this->get($this->admin('blog/posts'))->assertSee('Hello world')->assertDontSee('வணக்கம் உலகம்')->assertSee('TA');
        auth()->logout();

        $this->get('/blog?lang=ta')->assertOk()->assertSee('வணக்கம் உலகம்')->assertSee('/blog/hello-world', false);
        $this->get('/blog?lang=en')->assertOk()->assertSee('Hello world');
        $this->get('/blog/hello-world?lang=ta')->assertOk()->assertSee('வணக்கம் உலகம்');
        $this->get('/blog/hello-world-ta')->assertRedirect('/blog/hello-world?lang=ta');
    }

    public function test_deleting_the_original_removes_its_translations(): void
    {
        $page = $this->page();
        $translation = $page->makeTranslation('ta');

        $page->delete();

        $this->assertDatabaseMissing('pages', ['id' => $translation->id]);
    }

    public function test_menu_labels_are_translated(): void
    {
        $item = MenuItem::query()->create(['location' => 'header', 'label' => 'About', 'url' => '/about-us', 'sort_order' => 99, 'is_active' => true]);
        $this->actingAs($this->superAdmin());

        $this->put(route('admin.menus.update', $item), ['label' => 'About', 'url' => '/about-us', 'translations' => ['ta' => 'எங்களை பற்றி', 'fr' => 'ignored']])->assertRedirect();
        $this->assertSame(['ta' => 'எங்களை பற்றி'], $item->fresh()->translations);
        auth()->logout();

        $this->page();
        $this->get('/about-us?lang=ta')->assertSee('எங்களை பற்றி');
        $this->get('/about-us?lang=en')->assertSee('>About<', false);
    }

    public function test_single_language_site_has_no_switcher(): void
    {
        app(SettingsRepository::class)->set(['website_locales' => '']);
        $this->page();

        $this->get('/about-us?lang=ta')->assertOk()->assertDontSee('data-lang-switcher', false)->assertSee('lang="en"', false);
    }

    public function test_main_language_stays_in_the_switcher_when_another_is_chosen(): void
    {
        app(SettingsRepository::class)->set(['default_locale' => '']); // not saved yet: falls back to English
        $this->page();

        $this->get('/about-us?lang=ta')->assertOk()->assertSee('hreflang="en"', false)->assertSee('lang="ta"', false);
    }
}
