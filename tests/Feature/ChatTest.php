<?php

namespace Tests\Feature;

use App\Core\Settings\SettingsRepository;
use App\Models\User;
use App\Modules\Chat\Events\ChatEvent;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('chat');
    }

    /** Editors have chat.view (can reply) but not chat.start. */
    private function editor(string $name = 'Eddie Editor'): User
    {
        return $this->userWithRole('editor', ['name' => $name]);
    }

    private function startChat(User $from, User $to): int
    {
        return $this->actingAs($from)->postJson(route('admin.chat.start'), ['user_id' => $to->id])->assertOk()->json('conversation.id');
    }

    public function test_chat_page_and_top_bar_icon(): void
    {
        $this->actingAs($this->superAdmin())->get(route('admin.chat.index'))
            ->assertOk()->assertSee('Team chat')->assertSee('data-chat-unread', false);
    }

    public function test_people_without_chat_view_have_no_chat(): void
    {
        $user = $this->editor();
        $user->roles->first()->revokePermissionTo('chat.view');

        $this->actingAs($user->fresh())->get(route('admin.chat.index'))->assertForbidden();
    }

    public function test_search_lists_only_active_staff_with_chat_access(): void
    {
        $boss = $this->superAdmin();
        $this->editor('Priya Editor');
        $this->userWithRole('editor', ['name' => 'Priya Suspended', 'status' => 'suspended']);

        $names = collect($this->actingAs($boss)->getJson(route('admin.chat.users', ['q' => 'priya']))->assertOk()->json('users'))->pluck('name');

        $this->assertSame(['Priya Editor'], $names->all());
    }

    public function test_starting_a_chat_needs_chat_start_but_replying_does_not(): void
    {
        $boss = $this->superAdmin();
        $editor = $this->editor();
        $other = $this->editor('Other Editor');

        // editor can't search or start
        $this->actingAs($editor)->getJson(route('admin.chat.users', ['q' => 'a']))->assertForbidden();
        $this->actingAs($editor)->postJson(route('admin.chat.start'), ['user_id' => $other->id])->assertForbidden();

        // boss starts, editor replies
        $id = $this->startChat($boss, $editor);
        $this->actingAs($boss)->postJson(route('admin.chat.send', $id), ['body' => 'Hi Eddie 👋'])->assertOk();
        $this->actingAs($editor)->postJson(route('admin.chat.send', $id), ['body' => 'Hello boss'])->assertOk();

        // and the editor can reopen that existing chat from their side
        $this->assertSame($id, $this->startChat($editor, $boss));
        $this->assertSame(1, Conversation::query()->count());

        // giving the editor chat.start (extra permission) lets them start chats too
        $editor->givePermissionTo('chat.start');
        $this->startChat($editor->fresh(), $other);
        $this->assertSame(2, Conversation::query()->count());
    }

    public function test_messages_unread_and_read(): void
    {
        $boss = $this->superAdmin();
        $editor = $this->editor();
        $id = $this->startChat($boss, $editor);

        $this->actingAs($boss)->postJson(route('admin.chat.send', $id), ['body' => 'One'])->assertOk()->assertJsonPath('message.body', 'One');
        $this->actingAs($boss)->postJson(route('admin.chat.send', $id), ['body' => 'Two'])->assertOk();

        $this->actingAs($editor)->getJson(route('admin.chat.conversations'))->assertJsonPath('conversations.0.unread', 2)->assertJsonPath('conversations.0.user.name', $boss->name);
        $this->actingAs($editor)->get(route('admin.dashboard'))->assertSee('data-chat-unread="2"', false);

        $this->actingAs($editor)->getJson(route('admin.chat.messages', $id))->assertOk()->assertJsonCount(2, 'messages');
        $this->actingAs($editor)->postJson(route('admin.chat.read', $id))->assertJsonPath('unread_total', 0);

        $this->actingAs($boss)->getJson(route('admin.chat.messages', $id))->assertJsonPath('other_read', Message::query()->max('id'));
    }

    public function test_outsiders_cannot_read_or_post_or_download(): void
    {
        $boss = $this->superAdmin();
        $editor = $this->editor();
        $outsider = $this->userWithRole('admin');
        $id = $this->startChat($boss, $editor);

        $photo = $this->actingAs($boss)->post(route('admin.chat.send', $id), ['attachment' => UploadedFile::fake()->image('plan.png')], ['Accept' => 'application/json'])
            ->assertOk()->json('message.id');

        $this->actingAs($outsider)->getJson(route('admin.chat.messages', $id))->assertNotFound();
        $this->actingAs($outsider)->postJson(route('admin.chat.send', $id), ['body' => 'sneaky'])->assertNotFound();
        $this->actingAs($outsider)->get(route('admin.chat.attachment', $photo))->assertNotFound();
        $this->actingAs($editor)->get(route('admin.chat.attachment', $photo))->assertOk();
    }

    public function test_images_files_and_voice_notes_are_stored_privately(): void
    {
        $boss = $this->superAdmin();
        $id = $this->startChat($boss, $this->editor());

        $image = $this->actingAs($boss)->post(route('admin.chat.send', $id), ['attachment' => UploadedFile::fake()->image('photo.jpg')], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('image', $image->json('message.type'));

        $pdf = $this->actingAs($boss)->post(route('admin.chat.send', $id), ['attachment' => UploadedFile::fake()->create('quote.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('file', $pdf->json('message.type'));
        $this->assertSame('quote.pdf', $pdf->json('message.attachment.name'));

        $voice = $this->actingAs($boss)->post(route('admin.chat.send', $id), ['attachment' => UploadedFile::fake()->create('voice.webm', 30, 'audio/webm'), 'voice' => 1, 'duration' => 7], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('audio', $voice->json('message.type'));
        $this->assertSame(7, $voice->json('message.duration'));

        $paths = Message::query()->pluck('attachment_path');
        $this->assertCount(3, $paths);
        $paths->each(fn ($p) => Storage::disk('chat')->assertExists($p));
        $this->assertStringStartsWith('central/', (string) $paths->first());

        // a pdf is always downloaded, never shown inline
        $this->actingAs($boss)->get(route('admin.chat.attachment', $pdf->json('message.id')))->assertDownload('quote.pdf');
    }

    public function test_blocked_types_and_settings(): void
    {
        $boss = $this->superAdmin();
        $id = $this->startChat($boss, $this->editor());

        $this->actingAs($boss)->post(route('admin.chat.send', $id), ['attachment' => UploadedFile::fake()->create('evil.html', 1, 'text/html')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('attachment');

        app(SettingsRepository::class)->set(['chat_images' => false]);
        $this->actingAs($boss)->post(route('admin.chat.send', $id), ['attachment' => UploadedFile::fake()->image('p.png')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('attachment');

        app(SettingsRepository::class)->set(['chat_max_upload_mb' => 1]);
        $this->actingAs($boss)->post(route('admin.chat.send', $id), ['attachment' => UploadedFile::fake()->create('big.pdf', 2048, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('attachment');

        $this->actingAs($boss)->postJson(route('admin.chat.send', $id), ['body' => ''])->assertJsonValidationErrors('body');
    }

    public function test_delete_own_message_for_everyone(): void
    {
        $boss = $this->superAdmin();
        $editor = $this->editor();
        $id = $this->startChat($boss, $editor);
        $msg = $this->actingAs($boss)->post(route('admin.chat.send', $id), ['attachment' => UploadedFile::fake()->image('x.png')], ['Accept' => 'application/json'])->json('message');

        $this->actingAs($editor)->deleteJson(route('admin.chat.destroy', $msg['id']))->assertForbidden();
        $this->actingAs($boss)->deleteJson(route('admin.chat.destroy', $msg['id']))->assertOk()->assertJsonPath('message.type', 'deleted');

        $this->actingAs($editor)->getJson(route('admin.chat.messages', $id))->assertJsonPath('messages.0.type', 'deleted')->assertJsonPath('messages.0.attachment', null);
        $this->assertCount(0, Storage::disk('chat')->allFiles());
    }

    public function test_realtime_events_go_to_both_people(): void
    {
        config(['broadcasting.default' => 'reverb']);
        Event::fake([ChatEvent::class]);
        $boss = $this->superAdmin();
        $editor = $this->editor();
        $id = $this->startChat($boss, $editor);

        $this->actingAs($boss)->postJson(route('admin.chat.send', $id), ['body' => 'ping'])->assertOk();
        $this->actingAs($boss)->postJson(route('admin.chat.typing', $id))->assertOk();

        Event::assertDispatched(ChatEvent::class, fn (ChatEvent $e) => $e->broadcastAs() === 'chat.message'
            && collect($e->broadcastOn())->map->name->sort()->values()->all() === collect([$boss, $editor])->map(fn ($u) => 'private-'.$u->receivesBroadcastNotificationsOn())->sort()->values()->all());
        Event::assertDispatched(ChatEvent::class, fn (ChatEvent $e) => $e->broadcastAs() === 'chat.typing'
            && collect($e->broadcastOn())->map->name->all() === ['private-'.$editor->receivesBroadcastNotificationsOn()]);
    }

    public function test_old_messages_are_pruned_when_retention_is_set(): void
    {
        $boss = $this->superAdmin();
        $id = $this->startChat($boss, $this->editor());
        $this->actingAs($boss)->postJson(route('admin.chat.send', $id), ['body' => 'old'])->assertOk();
        Message::query()->update(['created_at' => now()->subDays(40)]);
        $this->actingAs($boss)->postJson(route('admin.chat.send', $id), ['body' => 'new'])->assertOk();

        $this->artisan('model:prune', ['--model' => [Message::class]]);
        $this->assertSame(2, Message::query()->count(), 'kept forever by default');

        app(SettingsRepository::class)->set(['chat_retention_days' => 30]);
        $this->artisan('model:prune', ['--model' => [Message::class]]);
        $this->assertSame(['new'], Message::query()->pluck('body')->all());
    }
}
