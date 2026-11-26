<?php

declare(strict_types=1);

namespace App\Modules\Inbox\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Export\TemplateExport;
use App\Core\Import\ImportRun;
use App\Http\Controllers\Controller;
use App\Modules\Inbox\Exports\SubscribersExport;
use App\Modules\Inbox\Imports\SubscribersImport;
use App\Modules\Inbox\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class SubscriberController extends Controller implements HasMiddleware
{
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
            ->export('subscribers', SubscribersExport::class, 'subscribers.export')   // app/Modules/Inbox/Exports/SubscribersExport.php
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

    /*
    | Import (Excel / CSV). Columns and saving: app/Modules/Inbox/Imports/SubscribersImport.php
    */
    public function importForm(): View
    {
        return view('inbox::admin.import', [
            'columns' => (new SubscribersImport)->describe(),
            'runs' => ImportRun::recent('subscribers'),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ImportRun::fileRules(), 'dry_run' => ['nullable', 'boolean']]);

        $run = ImportRun::start('subscribers', $request->file('file'), $request->user(), dryRun: $request->boolean('dry_run'));
        $run->process(new SubscribersImport($run));   // small file: now · big file: queued in chunks

        return redirect()->route('admin.subscribers.import')->with(
            $run->status === 'failed' ? 'error' : 'success',
            match (true) {
                $run->status === 'failed' => 'The file could not be imported: '.$run->message,
                $run->queued => 'Import started in the background ('.number_format($run->total_rows).' rows). You will get a notification when it is done.',
                $run->dry_run => 'Check finished, nothing was saved: '.$run->summary().'.',
                default => 'Import finished: '.$run->summary().'.',
            },
        );
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(new TemplateExport(new SubscribersImport), 'subscribers-import-template.xlsx');
    }
}
