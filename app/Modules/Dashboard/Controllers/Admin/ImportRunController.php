<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers\Admin;

use App\Core\Export\FailedRowsExport;
use App\Core\Import\ImportRun;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * One import's result page (the bell notification links here) and its progress as JSON
 * (the import pages poll it while a background import runs).
 */
class ImportRunController extends Controller
{
    public function show(Request $request, ImportRun $importRun): View|JsonResponse
    {
        $this->authorizeRun($request, $importRun);

        if ($request->wantsJson()) {
            return response()->json(self::progress($importRun));
        }

        return view('dashboard::admin.import-run', ['run' => $importRun->load('user:id,name'), 'skipped' => $importRun->errors()->orderBy('row')->limit(200)->get()]);
    }

    /** The skipped rows + reason, to fix and import again. */
    public function errors(Request $request, ImportRun $importRun): BinaryFileResponse
    {
        $this->authorizeRun($request, $importRun);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(new FailedRowsExport($importRun), Str::slug($importRun->module).'-import-'.$importRun->id.'-errors.'.$format);
    }

    /** @return array<string, mixed> */
    public static function progress(ImportRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'percent' => $run->percent(),
            'total' => $run->total_rows,
            'processed' => $run->processed_rows,
            'summary' => $run->summary(),
            'message' => $run->message,
        ];
    }

    private function authorizeRun(Request $request, ImportRun $run): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($run->visibleTo($user), 403);
    }
}
