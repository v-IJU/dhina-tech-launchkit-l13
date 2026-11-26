<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Import\ImportRun;
use App\Core\Import\RunImport;
use App\Core\Settings\SettingsRepository;
use App\Models\User;
use App\Modules\Faqs\Models\Faq;
use App\Modules\Inbox\Models\Subscriber;
use App\Notifications\WelcomeUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

        $this->get(route('admin.faqs.import'))->assertOk()->assertSee('Download template')->assertSee('Question')->assertSee('Recent imports');
        $template = $this->get(route('admin.faqs.import.template'))->assertOk()->assertDownload('faqs-import-template.xlsx');
        $rows = Excel::toArray(new HeadingRowImport, $template->baseResponse->getFile()->getPathname());
        $this->assertSame(['id', 'question', 'answer', 'group_name', 'sort_order', 'is_active'], $rows[0][0]);
    }

    public function test_import_creates_updates_and_reports_errors(): void
    {
        $boss = $this->superAdmin();
        $this->actingAs($boss);
        $existing = $this->faq('Old question');

        $csv = "ID,Question,Answer,Group name,Is active,Created\n"
            ."{$existing->id},Updated question,New answer,Billing,No,2026-01-01\n"
            .",Brand new?,Yes it is,General,Yes,\n"
            .",,Missing question,,,\n"
            .",,,,,\n";

        $this->post(route('admin.faqs.import.store'), ['file' => $this->csv($csv), 'dry_run' => 0])
            ->assertRedirect(route('admin.faqs.import'))
            ->assertSessionHas('success', 'Import finished: 1 created, 1 updated, 1 failed.');

        $this->assertSame('Updated question', $existing->fresh()->question);
        $this->assertFalse($existing->fresh()->is_active);
        $this->assertDatabaseHas('faqs', ['question' => 'Brand new?', 'group_name' => 'General', 'is_active' => true]);

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(['done', 3, 1], [$run->status, $run->processed_rows, $run->failed_rows]);
        $this->assertSame(4, $run->errors()->value('row'));

        // result page + skipped rows as a spreadsheet to fix
        $this->get(route('admin.imports.show', $run))->assertOk()->assertSee('The question field is required');
        $this->getJson(route('admin.imports.show', $run))->assertJson(['status' => 'done', 'percent' => 100]);
        $this->get(route('admin.imports.errors', $run))->assertDownload();
        $this->get(route('admin.faqs.import'))->assertSee('import.csv')->assertSee('1 created, 1 updated, 1 failed');
    }

    public function test_excel_file_is_imported(): void
    {
        $this->actingAs($this->superAdmin());
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([['Question', 'Answer', 'Sort order', 'Is active'], ['From Excel?', 'Yes', 3, 'yes'], ['=HYPERLINK("x")', 'Formula?', 4, 'no']]);
        (new Xlsx($sheet))->save($path);

        $this->post(route('admin.faqs.import.store'), ['file' => new UploadedFile($path, 'faqs.xlsx', null, null, true)])
            ->assertSessionHas('success', 'Import finished: 2 created.');
        $this->assertDatabaseHas('faqs', ['question' => 'From Excel?', 'sort_order' => 3, 'is_active' => true]);
    }

    public function test_dry_run_saves_nothing(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.faqs.import.store'), ['file' => $this->csv("Question;Answer\nCheck only?;Yes\n"), 'dry_run' => 1])
            ->assertSessionHas('success', 'Check finished, nothing was saved: 1 created.');

        $this->assertDatabaseMissing('faqs', ['question' => 'Check only?']);
    }

    public function test_wrong_headings_are_reported(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.faqs.import.store'), ['file' => $this->csv("Foo,Bar\n1,2\n")])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'None of the column headings match'));
    }

    public function test_big_files_run_in_the_background_and_notify(): void
    {
        config(['queue.default' => 'database']);
        app(SettingsRepository::class)->set(['import_queue_above' => 2, 'import_chunk_size' => 50]);
        $boss = $this->superAdmin();
        $this->actingAs($boss);

        $csv = "Question,Answer\n".collect(range(1, 120))->map(fn ($i) => "Question {$i}?,Answer {$i}")->implode("\n")."\n,no question\n";
        $this->post(route('admin.faqs.import.store'), ['file' => $this->csv($csv)])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'started in the background (121 rows)'));

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertTrue($run->queued);
        $this->assertSame(0, Faq::query()->count(), 'nothing imported yet: waiting for the queue');

        $this->artisan('queue:work', ['--stop-when-empty' => true, '--memory' => 4096, '--queue' => 'imports,default']); // high memory limit: the whole test suite runs in one process

        $run->refresh();
        $this->assertSame(['done', 121, 120, 1], [$run->status, $run->processed_rows, $run->created_rows, $run->failed_rows]);
        $this->assertSame(120, Faq::query()->count());
        $this->assertStringContainsString('Faqs import finished: 120 created, 1 failed', (string) $boss->notifications()->first()?->data['message']);
    }

    public function test_small_import_never_changes_the_queue_for_other_jobs(): void
    {
        config(['queue.default' => 'database']);   // like SQS in production
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.users.import.store'), ['file' => $this->csv("First name,Email,Username,Role\nAnita,anita@example.com,anita,editor\n")])
            ->assertSessionHas('success', 'Import finished: 1 created.');

        // imported right away, but the welcome email still went to the queue (not sent inside the request)
        $this->assertDatabaseHas('users', ['email' => 'anita@example.com']);
        $this->assertSame('database', config('queue.default'));
        $this->assertSame(1, DB::table('jobs')->where('payload', 'like', '%WelcomeUser%')->count());
    }

    public function test_big_import_is_one_job_on_the_default_queue(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        app(SettingsRepository::class)->set(['import_queue_above' => 1]);
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.faqs.import.store'), ['file' => $this->csv("Question,Answer\nA?,1\nB?,2\n")])->assertSessionHas('success');

        Queue::assertPushed(RunImport::class, 1);
        Queue::assertPushed(RunImport::class, fn ($job) => $job->connection === null && $job->queue === 'imports'); // default connection, own queue
    }

    public function test_chunk_import_works_on_whole_chunks_with_real_row_numbers(): void
    {
        app(SettingsRepository::class)->set(['import_chunk_size' => 50]);
        $this->actingAs($this->superAdmin());
        Subscriber::query()->create(['email' => 'known@example.com']);

        // 130 rows in chunks of 50 (rows 2-51, 52-101, 102-151). reader5 again in chunk 3 = already on the
        // list by then, so "updated"; reader120 twice inside chunk 3 = duplicate row; empty row ignored.
        $lines = collect(range(1, 120))->map(fn ($i) => "reader{$i}@example.com")
            ->push('KNOWN@example.com', 'not-an-email', '', 'reader5@example.com', 'reader120@example.com')
            ->push(...array_fill(0, 5, 'late@example.com'));
        $this->post(route('admin.subscribers.import.store'), ['file' => $this->csv("Email\n".$lines->implode("\n")."\n")])
            ->assertSessionHas('success', 'Import finished: 121 created, 2 updated, 6 failed.');

        $this->assertSame(122, Subscriber::query()->count());
        $run = ImportRun::query()->latest('id')->firstOrFail();
        $errors = $run->errors()->orderBy('row')->get();
        $this->assertSame([123, 126, 128, 129, 130, 131], $errors->pluck('row')->all());
        $this->assertStringContainsString('not a valid email', $errors->firstWhere('row', 123)->messages[0]);
        $this->assertStringContainsString('Same email as row 121', $errors->firstWhere('row', 126)->messages[0]);
        $this->assertStringContainsString('Same email as row 127', $errors->firstWhere('row', 131)->messages[0]);
    }

    public function test_chunk_import_runs_in_the_background_too(): void
    {
        config(['queue.default' => 'database']);
        app(SettingsRepository::class)->set(['import_queue_above' => 10, 'import_chunk_size' => 50]);
        $this->actingAs($this->superAdmin());

        $csv = "Email\n".collect(range(1, 200))->map(fn ($i) => "fan{$i}@example.com")->implode("\n")."\nbroken\n";
        $this->post(route('admin.subscribers.import.store'), ['file' => $this->csv($csv)])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'started in the background (201 rows)'));
        $this->assertSame(0, Subscriber::query()->count());

        $this->artisan('queue:work', ['--stop-when-empty' => true, '--memory' => 4096, '--queue' => 'imports,default']);

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(['done', 201, 200, 1], [$run->status, $run->processed_rows, $run->created_rows, $run->failed_rows]);
        $this->assertSame(202, $run->errors()->value('row'));
    }

    public function test_chunk_import_check_only_saves_nothing(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.subscribers.import.store'), ['file' => $this->csv("Email\na@example.com\nb@example.com\n"), 'dry_run' => 1])
            ->assertSessionHas('success', 'Check finished, nothing was saved: 2 created.');
        $this->assertSame(0, Subscriber::query()->count());
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
            ->assertSessionHas('success', 'Import finished: 1 created, 1 failed.');

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
            ->assertSessionHas('success', 'Import finished: 1 created, 1 updated, 1 failed.');

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'new@example.com']);
    }
}
