<?php

declare(strict_types=1);

namespace App\Modules\Ai\Mcp\Tools;

use App\Models\User;
use App\Modules\Inbox\Models\ContactMessage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Latest messages sent through the website\'s contact form (the Inbox), newest first. Use it to summarise enquiries or draft replies.')]
class ListEnquiriesTool extends SiteTool
{
    protected string $name = 'list-enquiries';

    protected ?string $module = 'inbox';

    protected ?string $permission = 'inbox.view';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'unread_only' => $schema->boolean()->description('Only messages nobody has opened yet. Default false.'),
            'limit' => $schema->integer()->description('How many (1-50). Default 10.'),
        ];
    }

    public function run(array $input, User $user): array
    {
        $limit = max(1, min(50, (int) ($input['limit'] ?? 10)));

        $messages = ContactMessage::query()
            ->when(filter_var($input['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->whereNull('read_at'))
            ->latest()->limit($limit)->get();

        return [
            'enquiries' => $messages->map(fn (ContactMessage $m) => [
                'id' => $m->id,
                'received_at' => $m->created_at?->toDateTimeString(),
                'name' => $this->person($m->name),
                'email' => $this->email($m->email),
                'phone' => $this->phone($m->phone),
                'subject' => $this->text($m->subject),
                'message' => $this->text(Str::limit((string) $m->message, 1000)),
                'read' => $m->read_at !== null,
                'replied' => $m->replied_at !== null,
            ])->all(),
        ];
    }
}
