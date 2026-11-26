<?php

declare(strict_types=1);

namespace App\Modules\Testimonials\Imports;

use App\Core\Import\ModuleImport;
use App\Modules\Testimonials\Models\Testimonial;
use Illuminate\Database\Eloquent\Model;

/**
 * Testimonials → Import (Excel or CSV). A row whose ID exists updates it, other rows are added.
 * Big files run in the background in chunks; see App\Core\Import\ModuleImport for what the base does.
 */
class TestimonialsImport extends ModuleImport
{
    protected ?string $model = Testimonial::class;

    protected string $matchBy = 'id';

    /** Columns of the file (headings) with an example, for the template and the help table. */
    public function columns(): array
    {
        return [
            'id' => '',
            'client_name' => 'Anita R.',
            'company' => 'Acme Ltd',
            'rating' => '5',
            'quote' => 'Great team to work with.',
            'is_active' => 'Yes',
        ];
    }

    public function rules(?Model $existing): array
    {
        return [
            'client_name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'quote' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function saveRow(array $data, ?Model $existing): void
    {
        $testimonial = $existing ?? new Testimonial;
        $testimonial->fill($data)->save();
    }
}
