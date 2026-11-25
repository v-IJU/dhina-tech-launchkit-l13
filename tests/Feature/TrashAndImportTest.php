<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Faqs\Models\Faq;
use App\Modules\Inbox\Models\Subscriber;
use App\Notifications\WelcomeUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TrashAndImportTest extends TestCase
{
    private function faq(string $question = 'Q?'): Faq
    {
        return Faq::query()->create(['question' => $question, 'answer' => 'A', 'is_active' => true]);
    }

    private function csv(string $content, string $name = 'import.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    // ----- Trash ------------------------------------------------------------------

    public function test_delete_moves_to_trash_and_restore_brings_it_back(): void
    {
        $this->actingAs($this->superAdmin());
        $faq = $this->faq('Will this come back?');

        $this->delete(route('admin.faqs.destroy', $faq))->assertRedirect();
        $this->assertSoftDeleted($faq);

        $this->get($this->admin('faqs'))->assertDontSee('Will this come back?')->assertSee('Trash');
        $this->get($this->admin('faqs?trashed=1'))->assertSee('Will this come back?')->assertSee('Delete forever');

        $this->patch(route('admin.faqs.restore', $faq))->assertRedirect();
        $this->assertNotSoftDeleted($faq);
    }

    public function test_force_delete_removes_the_row(): void
    {
        $this->actingAs($this->superAdmin());
        $faq = $this->faq();
        $faq->delete();

        $this->delete(route('admin.faqs.force-delete', $faq))->assertRedirect();
        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
    }

    public function test_bulk_restore_and_force_delete(): void
    {
        $this->actingAs($this->superAdmin());
        $a = $this->faq('A');
        $b = $this->faq('B');
        $a->delete();
        $b->delete();

        $this->post(route('admin.faqs.bulk'), ['action' => 'restore', 'ids' => [$a->id]])->assertRedirect();
        $this->assertNotSoftDeleted($a);

        $this->post(route('admin.faqs.bulk'), ['action' => 'force-delete', 'ids' => [$b->id]])->assertRedirect();
        $this->assertDatabaseMissing('faqs', ['id' => $b->id]);
    }

    public function test_trash_needs_delete_permission(): void
    {
        $editor = $this->userWithRole('editor'); // faqs.* but no *.delete
        $this->actingAs($editor);
        $faq = $this->faq();
        $faq->delete();

        $this->patch(route('admin.faqs.restore', $faq))->assertForbidden();
        $this->get($this->admin('faqs'))->assertOk()->assertDontSee('trashed=1', false);
    }

    public function test_users_and_posts_have_trash(): void
    {
        $this->actingAs($this->superAdmin());
        $user = $this->userWithRole('editor', ['first_name' => 'Trashy']);
        $user->delete();

        $this->get($this->admin('users?trashed=1'))->assertOk()->assertSee($user->email);
        $this->patch(route('admin.users.restore', $user))->assertRedirect();
        $this->assertNotSoftDeleted($user);

        $this->get($this->admin('blog/posts?trashed=1'))->assertOk()->assertSee('The trash is empty.');
    }

    public function test_empty_trash_command_removes_old_items_only(): void
    {
        $old = $this->faq('Old');
        $old->delete();
        $old->forceFill(['deleted_at' => now()->subDays(40)])->saveQuietly();
        $recent = $this->faq('Recent');
        $recent->delete();

        $this->artisan('trash:empty', ['--days' => 30])->assertSuccessful();

        $this->assertDatabaseMissing('faqs', ['id' => $old->id]);
        $this->assertSoftDeleted($recent);
    }

    // ----- Import -----------------------------------------------------------------

    public function test_import_page_and_template(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get(route('admin.faqs.import'))->assertOk()->assertSee('Download template')->assertSee('Question');
        $template = $this->get(route('admin.faqs.import.template'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Question', $template);
    }

    public function test_import_creates_updates_and_reports_errors(): void
    {
        $this->actingAs($this->superAdmin());
        $existing = $this->faq('Old question');

        $csv = "ID,Question,Answer,Group name,Is active,Created\n"
            ."{$existing->id},Updated question,New answer,Billing,No,2026-01-01\n"
            .",Brand new?,Yes it is,General,Yes,\n"
            .",,Missing question,,,\n"
            .",,,,,\n";

        $this->post(route('admin.faqs.import.store'), ['file' => $this->csv($csv), 'dry_run' => 0])
            ->assertOk()
            ->assertSee('1 created, 1 updated, 1 skipped (empty), 1 with errors', false)
            ->assertSee('Line 4');

        $this->assertSame('Updated question', $existing->fresh()->question);
        $this->assertFalse($existing->fresh()->is_active);
        $this->assertDatabaseHas('faqs', ['question' => 'Brand new?', 'group_name' => 'General', 'is_active' => true]);
    }

    public function test_dry_run_saves_nothing(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.faqs.import.store'), ['file' => $this->csv("Question;Answer\nCheck only?;Yes\n"), 'dry_run' => 1])
            ->assertOk()->assertSee('Nothing was saved');

        $this->assertDatabaseMissing('faqs', ['question' => 'Check only?']);
    }

    public function test_wrong_headings_are_reported(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.faqs.import.store'), ['file' => $this->csv("Foo,Bar\n1,2\n")])
            ->assertOk()->assertSee('None of the column headings match');
    }

    public function test_import_needs_permission(): void
    {
        $user = User::query()->create([
            'name' => 'No Import', 'first_name' => 'No', 'last_name' => 'Import', 'username' => 'noimport',
            'email' => 'noimport@example.com', 'password' => bcrypt('Secret#123'), 'status' => 'active',
        ]);
        $user->givePermissionTo('faqs.view');
        $this->actingAs($user);

        $this->get(route('admin.faqs.import'))->assertForbidden();
        $this->get($this->admin('faqs'))->assertDontSee(route('admin.faqs.import'));
    }

    public function test_user_import_creates_users_with_role_and_welcome_email(): void
    {
        Notification::fake();
        $this->actingAs($this->superAdmin());

        $csv = "First name,Last name,Email,Username,Role,Status\n"
            ."Anita,Raj,ANITA@example.com,anita,editor,active\n"
            ."Bad,Role,bad@example.com,bad,owner,active\n";

        $this->post(route('admin.users.import.store'), ['file' => $this->csv($csv), 'dry_run' => 0])
            ->assertOk()->assertSee('1 created');

        $anita = User::query()->where('email', 'anita@example.com')->firstOrFail();
        $this->assertTrue($anita->hasRole('editor'));
        $this->assertTrue((bool) $anita->force_password_reset);
        Notification::assertSentTo($anita, WelcomeUser::class);
    }

    public function test_subscriber_import_skips_duplicates_as_updates(): void
    {
        $this->actingAs($this->superAdmin());
        Subscriber::query()->create(['email' => 'known@example.com']);

        $this->post(route('admin.subscribers.import.store'), ['file' => $this->csv("Email\nknown@example.com\nnew@example.com\nnot-an-email\n"), 'dry_run' => 0])
            ->assertOk()->assertSee('1 created, 1 updated', false);

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'new@example.com']);
    }
}
