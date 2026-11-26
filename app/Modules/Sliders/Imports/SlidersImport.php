<?php

declare(strict_types=1);

namespace App\Modules\Sliders\Imports;

use App\Core\Import\ModuleImport;
use App\Modules\Sliders\Models\Slider;
use Illuminate\Database\Eloquent\Model;

/**
 * Sliders → Import (Excel or CSV). A row whose ID exists updates it, other rows are added.
 * Big files run in the background in chunks; see App\Core\Import\ModuleImport for what the base does.
 */
class SlidersImport extends ModuleImport
{
    protected ?string $model = Slider::class;

    protected string $matchBy = 'id';

    /** Columns of the file (headings) with an example, for the template and the help table. */
    public function columns(): array
    {
        return [
            'id' => '',
            'title' => 'Spring offer',
            'subtitle' => '',
            'button_text' => 'Shop now',
            'button_url' => '/offers',
            'sort_order' => '1',
            'is_active' => 'Yes',
        ];
    }

    public function rules(?Model $existing): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:255'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function saveRow(array $data, ?Model $existing): void
    {
        $slider = $existing ?? new Slider;
        $slider->fill($data)->save();
    }
}
