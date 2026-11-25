<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use App\Modules\Faqs\Models\Faq;
use App\Modules\Pages\Models\Page;
use App\Modules\Services\Models\Service;
use App\Modules\Sliders\Models\Slider;
use App\Modules\Testimonials\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * Starter website content so the site is not empty on day one.
 * Replace the [placeholders] with the client's real text.
 * Skipped automatically when pages already exist.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        if (! class_exists(Page::class) || Page::query()->exists()) {
            return;
        }

        $this->pages();

        if (class_exists(Service::class)) {
            foreach ([
                ['Web applications', 'bi-code-slash', 'Custom web apps and admin panels built to fit how your business works.'],
                ['Mobile apps', 'bi-phone', 'iOS and Android apps connected to your systems.'],
                ['Cloud & DevOps', 'bi-cloud-check', 'Hosting, deployment pipelines, backups and monitoring.'],
                ['Business software', 'bi-diagram-3', 'CRM, ERP and internal tools that replace spreadsheets.'],
                ['Branding & design', 'bi-palette', 'Logos, websites and interfaces people enjoy using.'],
                ['Support & maintenance', 'bi-life-preserver', 'Updates, fixes and improvements after launch.'],
            ] as $i => [$title, $icon, $summary]) {
                Service::query()->create(['title' => $title, 'icon' => $icon, 'summary' => $summary, 'sort_order' => $i + 1, 'is_active' => true]);
            }
        }

        if (class_exists(Testimonial::class)) {
            foreach ([['[Client name]', '[Company]'], ['[Client name]', '[Company]'], ['[Client name]', '[Company]']] as [$name, $company]) {
                Testimonial::query()->create(['client_name' => $name, 'company' => $company, 'rating' => 5, 'quote' => '[Replace with a real quote from a client about working with you.]', 'is_active' => true]);
            }
        }

        if (class_exists(Faq::class)) {
            foreach ([
                ['How long does a typical project take?', 'Most websites take 4 to 8 weeks. Larger web and mobile apps are planned in phases.'],
                ['Do you offer support after launch?', 'Yes. Monthly support plans cover updates, backups, monitoring and small changes.'],
                ['Can you work with our existing system?', 'Yes. We regularly connect new work to existing databases, APIs and tools.'],
                ['How do we get started?', 'Send us a message using the contact form. We will reply within one business day.'],
            ] as $i => [$q, $a]) {
                Faq::query()->create(['question' => $q, 'answer' => $a, 'group_name' => 'General', 'sort_order' => $i + 1, 'is_active' => true]);
            }
        }

        if (class_exists(Slider::class)) {
            Slider::query()->create(['title' => 'Grow your business online', 'subtitle' => 'Websites, apps and cloud — built and supported by one team.', 'button_text' => 'Get in touch', 'button_url' => '/contact', 'sort_order' => 1, 'is_active' => true]);
        }

        if (class_exists(Post::class)) {
            $news = Category::query()->create(['name' => 'News', 'description' => 'Company announcements']);
            Category::query()->create(['name' => 'Guides', 'description' => 'How-to articles']);
            $author = User::query()->orderBy('id')->first();

            Post::query()->create([
                'title' => 'Welcome to our new website',
                'excerpt' => 'Our new site is live. Here is what you can find on it.',
                'content' => '<p>We have launched our new website. You can read about our <a href="/services">services</a>, see answers to common questions and get in touch using the <a href="/contact">contact form</a>.</p><h2>What is next</h2><p>We will share guides and updates on this blog. Subscribe to the newsletter in the footer to hear about new posts.</p>',
                'category_id' => $news->id,
                'author_id' => $author?->id,
                'status' => 'published',
                'published_at' => now(),
                'tags' => ['news', 'launch'],
            ]);
        }
    }

    private function pages(): void
    {
        $home = [
            ['type' => 'hero', 'data' => [
                'heading' => 'Software that helps your business grow',
                'subheading' => 'We design, build and support websites, mobile apps and business systems — all under one team.',
                'button_text' => 'Start a project', 'button_url' => '/contact',
                'secondary_text' => 'Our services', 'secondary_url' => '/services', 'use_sliders' => false,
            ]],
            ['type' => 'features', 'data' => ['eyebrow' => 'What we do', 'heading' => 'Everything you need, from idea to launch', 'subheading' => 'Pick one service or let us handle the whole project.']],
            ['type' => 'stats', 'data' => ['heading' => '', 'items' => "[00]+|Projects delivered\n[00]+|Happy clients\n[00]|Years in business\n24/7|Monitoring"]],
            ['type' => 'testimonials', 'data' => ['heading' => 'What clients say', 'subheading' => '']],
            ['type' => 'latest_posts', 'data' => ['heading' => 'Latest from the blog', 'count' => 3]],
            ['type' => 'cta', 'data' => ['heading' => 'Have a project in mind?', 'text' => 'Tell us what you need. We reply within one business day.', 'button_text' => 'Contact us', 'button_url' => '/contact']],
        ];

        Page::query()->create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'is_home' => true, 'blocks' => $home]);

        Page::query()->create(['title' => 'About us', 'slug' => 'about', 'status' => 'published', 'blocks' => [
            ['type' => 'hero', 'data' => ['heading' => 'About us', 'subheading' => '[One or two sentences about who you are and who you help.]']],
            ['type' => 'image_text', 'data' => ['heading' => 'Our story', 'body' => '<p>[Tell the story of the company: when it started, what you believe in and how you work with clients.]</p>', 'image_position' => 'left']],
            ['type' => 'stats', 'data' => ['heading' => 'In numbers', 'items' => "[00]+|Projects\n[00]|Team members\n[00]|Countries served"]],
            ['type' => 'cta', 'data' => ['heading' => 'Want to work with us?', 'text' => '', 'button_text' => 'Get in touch', 'button_url' => '/contact']],
        ]]);

        Page::query()->create(['title' => 'Services', 'slug' => 'services', 'status' => 'published', 'blocks' => [
            ['type' => 'hero', 'data' => ['heading' => 'Our services', 'subheading' => 'From a simple website to a complete business platform.']],
            ['type' => 'features', 'data' => ['heading' => 'What we offer']],
            ['type' => 'faq', 'data' => ['heading' => 'Frequently asked questions']],
            ['type' => 'cta', 'data' => ['heading' => 'Not sure what you need?', 'text' => 'Describe the problem and we will suggest the simplest solution.', 'button_text' => 'Ask us', 'button_url' => '/contact']],
        ]]);

        Page::query()->create(['title' => 'Contact', 'slug' => 'contact', 'status' => 'published', 'blocks' => [
            ['type' => 'contact_form', 'data' => ['heading' => 'Get in touch', 'text' => 'Tell us about your project and we will reply within one business day.']],
            ['type' => 'map', 'data' => ['heading' => '']],
        ]]);

        Page::query()->create(['title' => 'Privacy policy', 'slug' => 'privacy-policy', 'status' => 'published', 'blocks' => [
            ['type' => 'text', 'data' => ['heading' => 'Privacy policy', 'body' => '<p>[Your privacy policy. Explain what personal data you collect through the contact form and newsletter, why, how long you keep it and how people can ask for it to be deleted.]</p>']],
        ]]);
    }
}
