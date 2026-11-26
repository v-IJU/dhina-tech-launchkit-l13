<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Faqs\Models\Faq;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DataTableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (range(1, 30) as $i) {
            $faq = Faq::query()->create([
                'question' => sprintf('Question %02d', $i),
                'answer' => 'Answer',
                'group_name' => $i <= 10 ? 'Billing' : 'General',
                'sort_order' => 31 - $i,
                'is_active' => $i % 2 === 0,
            ]);
            $faq->forceFill(['created_at' => now()->subDays($i)])->save();
        }
    }

    public function test_search_filter_sort_and_per_page(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get($this->admin('faqs?per_page=10&sort=question&dir=asc'))
            ->assertOk()
            ->assertSeeInOrder(['Question 01', 'Question 02', 'Question 03'])
            ->assertSee('Showing 1–10 of 30', false);

        $this->get($this->admin('faqs?sort=question&dir=desc&per_page=10'))
            ->assertSeeInOrder(['Question 30', 'Question 29']);

        $this->get($this->admin('faqs?status=1&per_page=100'))
            ->assertSee('Question 02')->assertDontSee('Question 01')
            ->assertSee('of 15', false);

        $this->get($this->admin('faqs?q=Billing'))->assertSee('of 10', false);

        // Unknown sort column and odd per-page values fall back to safe defaults.
        $this->get($this->admin('faqs?sort=password&per_page=9999'))->assertOk()->assertSee('of 30', false);
    }

    public function test_date_range_filter(): void
    {
        $this->actingAs($this->superAdmin());

        $from = now()->subDays(5)->toDateString();
        $this->get($this->admin("faqs?from={$from}&per_page=100"))->assertSee('of 5', false);
    }

    public function test_ajax_request_returns_only_the_rows(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get($this->admin('faqs?per_page=10'), ['X-DataTable' => '1'])
            ->assertOk()
            ->assertDontSee('<html', false)
            ->assertSee('data-datatable-per-page', false)
            ->assertSee('Question');
    }

    public function test_out_of_range_page_shows_the_last_page(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get($this->admin('faqs?per_page=25&page=9'))->assertSee('Showing 26–30', false);
    }

    public function test_csv_export_uses_current_filters(): void
    {
        $this->actingAs($this->superAdmin());
        Faq::query()->first()->update(['question' => '=HYPERLINK("http://evil")', 'is_active' => true]);

        $csv = $this->get($this->admin('faqs?export=csv&status=1'))->assertOk()->assertDownload()->baseResponse->getFile()->getContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        $this->assertStringContainsString('Question', $lines[0]);
        $this->assertCount(1 + Faq::query()->where('is_active', true)->count(), $lines);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'formulas are neutralised');

        // Excel: a real .xlsx with the same rows, the formula stored as plain text
        $file = $this->get($this->admin('faqs?export=xlsx&status=1'))->assertOk()->baseResponse->getFile()->getPathname();
        $sheet = IOFactory::load($file)->getActiveSheet();
        $this->assertSame('Question', $sheet->getCell('B1')->getValue());
        $cells = collect($sheet->toArray())->flatten();
        $this->assertTrue($cells->contains('=HYPERLINK("http://evil")'));
        $this->assertSame(DataType::TYPE_STRING, collect($sheet->getRowIterator())->flatMap(fn ($r) => iterator_to_array($r->getCellIterator()))->first(fn ($c) => $c->getValue() === '=HYPERLINK("http://evil")')?->getDataType());
    }

    public function test_export_needs_permission(): void
    {
        $user = $this->plainUser();
        $user->givePermissionTo('faqs.view');
        $this->actingAs($user);

        $response = $this->get($this->admin('faqs?export=csv'))->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $response->assertDontSee('data-datatable-export', false);
    }

    public function test_bulk_activate_deactivate_and_delete(): void
    {
        $this->actingAs($this->superAdmin());
        $ids = Faq::query()->where('is_active', false)->pluck('id')->all();

        $this->post(route('admin.faqs.bulk'), ['action' => 'activate', 'ids' => $ids])->assertRedirect();
        $this->assertSame(30, Faq::query()->where('is_active', true)->count());

        $this->post(route('admin.faqs.bulk'), ['action' => 'deactivate', 'ids' => $ids])->assertRedirect();
        $this->assertSame(15, Faq::query()->where('is_active', true)->count());

        $this->post(route('admin.faqs.bulk'), ['action' => 'delete', 'ids' => $ids])->assertRedirect();
        $this->assertSame(15, Faq::query()->count());

        $this->post(route('admin.faqs.bulk'), ['action' => 'explode', 'ids' => $ids])->assertSessionHasErrors('action');
    }

    public function test_bulk_delete_needs_delete_permission(): void
    {
        $user = $this->plainUser();
        $user->givePermissionTo(['faqs.view', 'faqs.edit']);
        $this->actingAs($user);

        $id = Faq::query()->value('id');
        $this->post(route('admin.faqs.bulk'), ['action' => 'delete', 'ids' => [$id]])->assertForbidden();
        $this->assertDatabaseHas('faqs', ['id' => $id]);

        // Without delete permission the Delete button is not offered.
        $this->get($this->admin('faqs'))->assertSee('Deactivate')->assertDontSee('data-confirm="Delete the selected items?"', false);
    }

    public function test_users_and_posts_lists_use_the_data_table(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get($this->admin('users?status=active&sort=name&dir=asc'))->assertOk()->assertSee('data-datatable', false);
        $this->get($this->admin('blog/posts?sort=title'))->assertOk()->assertSee('data-datatable', false);
        $this->get($this->admin('pages?status=published'))->assertOk()->assertSee('data-datatable', false);
        $this->get($this->admin('subscribers'))->assertOk()->assertSee('data-datatable', false);
        $this->get($this->admin('activity?event=login'))->assertOk()->assertSee('data-datatable', false);

        $csv = $this->get($this->admin('blog/posts?export=csv'))->assertOk()->assertDownload()->baseResponse->getFile()->getContent();
        $this->assertStringContainsString('Title', $csv);
    }

    private function plainUser(): User
    {
        return User::query()->create([
            'name' => 'Plain User', 'first_name' => 'Plain', 'last_name' => 'User', 'username' => 'plain-'.uniqid(),
            'email' => 'plain-'.uniqid().'@example.com', 'password' => Hash::make('Secret#123'), 'status' => 'active',
        ]);
    }
}
