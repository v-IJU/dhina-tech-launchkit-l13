<?php

declare(strict_types=1);

namespace App\Modules\Ai\Mcp\Tools;

use App\Models\User;
use App\Modules\Blog\Models\Post;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inbox\Models\ContactMessage;
use App\Modules\Pages\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Overview of this website and admin panel: name, address, and how many users, pages, blog posts, customers and unread enquiries it has. Only counts what the signed-in user may see.')]
class SiteOverviewTool extends SiteTool
{
    protected string $name = 'site-overview';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function run(array $input, User $user): array
    {
        $counts = array_filter([
            'users' => $user->can('users.view') ? User::query()->count() : null,
            'pages' => module_enabled('pages') && $user->can('pages.view') ? Page::query()->count() : null,
            'blog_posts_published' => module_enabled('blog') && $user->can('blog.view') ? Post::query()->published()->count() : null,
            'blog_posts_draft' => module_enabled('blog') && $user->can('blog.view') ? Post::query()->where('status', 'draft')->count() : null,
            'customers' => module_enabled('customers') && $user->can('customers.view') ? Customer::query()->count() : null,
            'enquiries_unread' => module_enabled('inbox') && $user->can('inbox.view') ? ContactMessage::query()->whereNull('read_at')->count() : null,
        ], fn ($v) => $v !== null);

        return [
            'site_name' => (string) setting('site_name', config('app.name')),
            'website_url' => url('/'),
            'admin_url' => url((string) config('starter.admin_path')),
            'signed_in_as' => ['name' => $this->person($user->name), 'email' => $this->email($user->email), 'roles' => $user->getRoleNames()->all()],
            'counts' => $counts,
        ];
    }
}
