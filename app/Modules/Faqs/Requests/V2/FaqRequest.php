<?php

declare(strict_types=1);

namespace App\Modules\Faqs\Requests\V2;

use App\Modules\Faqs\Requests\StoreFaqRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * v2 field names → the v1 rules: title→question, body→answer, group→group_name,
 * position→sort_order, active→is_active. Validation stays in one place (StoreFaqRequest).
 */
class FaqRequest extends StoreFaqRequest
{
    private const MAP = [
        'title' => 'question',
        'body' => 'answer',
        'group' => 'group_name',
        'position' => 'sort_order',
        'active' => 'is_active',
    ];

    protected function prepareForValidation(): void
    {
        foreach (self::MAP as $v2 => $v1) {
            if ($this->has($v2)) {
                $this->merge([$v1 => $this->input($v2)]);
            }
        }
    }

    /**
     * Error messages say "title" instead of "question".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_flip(self::MAP);
    }

    /**
     * 422 errors keyed by the v2 names, so the app can show them under its own fields.
     */
    protected function failedValidation(Validator $validator): void
    {
        $names = array_flip(self::MAP);
        $errors = collect($validator->errors()->toArray())->mapWithKeys(fn ($messages, $field) => [$names[$field] ?? $field => $messages]);

        throw new HttpResponseException(response()->json(['message' => $validator->errors()->first(), 'errors' => $errors], 422));
    }
}