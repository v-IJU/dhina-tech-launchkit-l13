<?php

declare(strict_types=1);

namespace App\Modules\Blog\Console;

use App\Modules\Blog\Models\Post;
use Illuminate\Console\Command;

/**
 * Runs every minute from the scheduler. Scheduled posts are already visible
 * once their time passes; this marks them "published" so lists and counts
 * stay accurate and the change is logged.
 */
class PublishScheduledPosts extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = 'Mark scheduled blog posts whose time has come as published';

    public function handle(): int
    {
        $posts = Post::query()->where('status', 'scheduled')->where('published_at', '<=', now())->get();

        foreach ($posts as $post) {
            $post->update(['status' => 'published']);
        }

        $this->components->info("{$posts->count()} post(s) published.");

        return self::SUCCESS;
    }
}
