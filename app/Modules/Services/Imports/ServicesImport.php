<?php

declare(strict_types=1);

namespace App\Modules\Services\Imports;

use App\Core\Import\ModuleImport;
use App\Modules\Services\Models\Service;
use Illuminate\Database\Eloquent\Model;

/**
 * Services → Import (Excel or CSV). A row whose ID exists updates it, other rows are added.
 * Big files run in the background in chunks; see App\Core\Import\ModuleImport for what the base does.
 */
class ServicesImport extends ModuleImport
{
    protected ?string $model = Service::class;

    protected string $matchBy = 'id';

    /** Columns of the file (headings) with an example, for the template and the help table. */
    public function columns(): array
    {
        return [
            'id' => '',
            'title' => 'Web development',
            'icon' => 'bi-code-slash',
            'summary' => 'Fast, secure websites.',
            'sort_order' => '1',
            'is_active' => 'Yes',
        ];
    }

    public function rules(?Model $existing): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function saveRow(array $data, ?Model $existing): void
    {
        $service = $existing ?? new Service;
        $service->fill($data)->save();
    }
}
