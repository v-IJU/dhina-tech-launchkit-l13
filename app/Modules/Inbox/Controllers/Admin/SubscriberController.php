<?php

declare(strict_types=1);

namespace App\Modules\Inbox\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Import\CsvImport;
use App\Core\Import\HandlesImport;
use App\Http\Controllers\Controller;
use App\Modules\Inbox\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SubscriberController extends Controller implements HasMiddleware
{
    use HandlesImport;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:subscribers.view', only: ['index']),
            new Middleware('permission:subscribers.delete', only: ['destroy']),
            new Middleware('permission:subscribers.import', only: ['importForm', 'import', 'importTemplate']),
        ];
    }

    public function index(Request $request): Response
    {
        return DataTable::of(Subscriber::query(), $request)
            ->search(['email'])
            ->sortable(['email', 'created_at'], default: 'created_at')
            ->dateRange('created_at', 'Subscribed')
            ->export('subscribers', ['Email' => 'email', 'Subscribed at' => 'created_at'], 'subscribers.export')
            ->bulk(route('admin.subscribers.bulk'), ['delete' => 'subscribers.delete'])
            ->render('inbox::admin.subscribers', 'inbox::admin._subscribers_table');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);
        abort_unless($request->user()->can('subscribers.delete'), 403);

        $count = Subscriber::query()->whereKey($data['ids'])->delete();

        return back()->with('success', "{$count} subscriber(s) removed.");
    }

    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('success', "{$subscriber->email} removed.");
    }

    /**
     * @return CsvImport<Subscriber>
     */
    protected function importer(): CsvImport
    {
        return CsvImport::make(Subscriber::class)
            ->matchBy('email')
            ->column('email', fn (?Subscriber $s) => ['required', 'email', 'max:255', Rule::unique(Subscriber::class, 'email')->ignore($s)], example: 'reader@example.com')
            ->prepare(fn (array $data) => ['email' => strtolower($data['email'])]);
    }

    /**
     * @return array{title: string, slug: string, back: string, breadcrumbs: array<int, string>}
     */
    protected function importPage(): array
    {
        return ['title' => 'Subscribers', 'slug' => 'subscribers', 'back' => route('admin.subscribers.index'), 'breadcrumbs' => ['Website CMS', 'Subscribers', 'Import']];
    }
}
