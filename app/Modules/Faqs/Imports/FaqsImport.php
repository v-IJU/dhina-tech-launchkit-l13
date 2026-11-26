<?php

declare(strict_types=1);

namespace App\Modules\Faqs\Imports;

use App\Core\Import\ModuleImport;
use App\Modules\Faqs\Models\Faq;
use Illuminate\Database\Eloquent\Model;

/**
 * FAQs → Import (Excel or CSV). A row with a known ID updates that FAQ, other rows are added.
 * Big files run in the background in chunks; see App\Core\Import\ModuleImport for what the base does.
 */
class FaqsImport extends ModuleImport
{
    protected ?string $model = Faq::class;

    protected string $matchBy = 'id';

    /** Columns of the file (headings) with an example, for the template and the help table. */
    public function columns(): array
    {
        return [
            'id' => '',
            'question' => 'How long does a project take?',
            'answer' => 'Usually 4 to 8 weeks.',
            'group_name' => 'General',
            'sort_order' => '1',
            'is_active' => 'Yes',
        ];
    }

    public function rules(?Model $existing): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'group_name' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function saveRow(array $data, ?Model $existing): void
    {
        $faq = $existing ?? new Faq;
        $faq->fill($data)->save();
    }
}
