<?php

declare(strict_types=1);

namespace App\Modules\Ai\Mcp\Tools;

use App\Models\User;
use App\Modules\Blog\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Attributes\Description;
use Mews\Purifier\Facades\Purifier;

#[Description('Create a new blog post as a DRAFT (never published automatically). A person reviews and publishes it in the admin panel. Returns the edit link.')]
class CreateBlogDraftTool extends SiteTool
{
    protected string $name = 'create-blog-draft';

    protected ?string $module = 'blog';

    protected ?string $permission = 'blog.create';

    protected bool $writes = true;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Post title, max 200 characters.')->required(),
            'content' => $schema->string()->description('Post body. Simple HTML (<p>, <h2>, <ul>, <strong>, <a>) or plain text.')->required(),
            'excerpt' => $schema->string()->description('One or two sentence summary for lists and search results.'),
        ];
    }

    public function run(array $input, User $user): array
    {
        $title = Str::limit(trim(strip_tags((string) ($input['title'] ?? ''))), 200, '');
        $content = (string) ($input['content'] ?? '');
        if ($title === '' || trim($content) === '') {
            return ['created' => false, 'error' => 'Both title and content are required.'];
        }
        if ($content === strip_tags($content)) {
            $content = '<p>'.implode('</p><p>', array_map('e', preg_split('/\R{2,}/', trim($content)) ?: [])).'</p>';
        }

        $post = Post::query()->create([
            'title' => $title,
            'content' => Purifier::clean($content),
            'excerpt' => isset($input['excerpt']) ? Str::limit(strip_tags((string) $input['excerpt']), 500, '') : null,
            'status' => 'draft',
            'author_id' => $user->id,
        ]);

        activity()->performedOn($post)->causedBy($user)->log('created a draft with an AI client');

        return [
            'created' => true,
            'id' => $post->id,
            'title' => $post->title,
            'status' => 'draft',
            'edit_url' => route('admin.blog.posts.edit', $post),
        ];
    }
}
