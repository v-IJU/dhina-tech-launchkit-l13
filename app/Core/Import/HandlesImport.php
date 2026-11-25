<?php

declare(strict_types=1);

namespace App\Core\Import;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adds "Import CSV" to an admin controller. The controller defines importer()
 * and importPage(); the page, upload and template download come from here.
 *
 * Routes (before Route::resource):
 *   Route::get('faqs/import', [FaqController::class, 'importForm'])->name('faqs.import');
 *   Route::post('faqs/import', [FaqController::class, 'import'])->name('faqs.import.store');
 *   Route::get('faqs/import/template', [FaqController::class, 'importTemplate'])->name('faqs.import.template');
 *
 * Permission: protect these three methods with "<slug>.import" in middleware().
 */
trait HandlesImport
{
    /**
     * @return CsvImport<Model>
     */
    abstract protected function importer(): CsvImport;

    /**
     * @return array{title: string, slug: string, back: string, breadcrumbs?: array<int, string>, help?: string}
     */
    abstract protected function importPage(): array;

    public function importForm(): View
    {
        return view('admin.import', $this->importPage() + ['columns' => $this->importer()->describe(), 'result' => null]);
    }

    public function import(Request $request): View
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.config('starter.import_max_kb', 5120)],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $result = $this->importer()->run($request->file('file'), $request->boolean('dry_run'));

        if (! $result->dryRun && ($result->created || $result->updated)) {
            activity($this->importPage()['slug'])->causedBy($request->user())
                ->withProperties(['created' => $result->created, 'updated' => $result->updated, 'failed' => $result->failed()])
                ->event('import')->log('Imported '.$request->file('file')->getClientOriginalName());
        }

        return view('admin.import', $this->importPage() + ['columns' => $this->importer()->describe(), 'result' => $result]);
    }

    public function importTemplate(): StreamedResponse
    {
        return $this->importer()->template($this->importPage()['slug']);
    }
}
