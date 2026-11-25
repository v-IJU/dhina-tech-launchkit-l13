<?php

declare(strict_types=1);

namespace App\Modules\Blog\Database\Seeders;

use App\Models\User;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use Illuminate\Database\Seeder;

/**
 * Sample blog posts for testing the list page: pagination, search, filters, sorting, export.
 *
 * php artisan db:seed --class="App\Modules\Blog\Database\Seeders\BlogPostSeeder"
 */
class BlogPostSeeder extends Seeder
{
    private const COUNT = 120;

    public function run(): void
    {
        $categories = collect(['News', 'Tutorials', 'Case studies', 'Company', 'Events'])
            ->map(fn (string $name) => Category::query()->firstOrCreate(['name' => $name]))
            ->pluck('id')
            ->all();

        $authors = User::query()->pluck('id')->all();
        $start = Post::query()->where('slug', 'like', 'sample-post-%')->count();
        $topics = ['Laravel', 'Docker', 'Design', 'Marketing', 'Security', 'Performance', 'Hiring', 'Cloud'];

        foreach (range(1, self::COUNT) as $i) {
            $n = $start + $i;
            $status = ['published', 'published', 'published', 'draft', 'scheduled'][$n % 5];
            $date = now()->subDays(random_int(0, 365))->setTime(random_int(8, 18), random_int(0, 59));

            if ($status === 'scheduled') {
                $date = now()->addDays(random_int(1, 30));
            }

            $topic = $topics[$n % count($topics)];

            Post::query()->create([
                'title' => "{$topic} tips #{$n}: ".fake()->sentence(4),
                'slug' => "sample-post-{$n}",
                'excerpt' => fake()->sentence(14),
                'content' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
                'category_id' => $categories[array_rand($categories)],
                'author_id' => $authors ? $authors[array_rand($authors)] : null,
                'status' => $status,
                'published_at' => $status === 'draft' ? null : $date,
                'is_featured' => $n % 9 === 0,
                'tags' => [strtolower($topic), fake()->word()],
            ])->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();
        }

        $this->command?->info(self::COUNT.' sample posts added.');
    }
}