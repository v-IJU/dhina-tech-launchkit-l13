<?php

declare(strict_types=1);

namespace App\Core\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Saves uploaded files from a form onto the public disk and removes the old
 * file when it is replaced.
 */
trait StoresUploads
{
    /**
     * @param  array<string, mixed>  $data  validated input
     * @param  array<int, string>  $fields  file input names
     * @return array<string, mixed>
     */
    protected function storeUploads(Request $request, array $data, array $fields, string $folder, ?Model $model = null): array
    {
        foreach ($fields as $field) {
            unset($data[$field]);

            if ($request->hasFile($field)) {
                if ($model?->{$field}) {
                    Storage::disk('uploads')->delete($model->{$field});
                }
                $data[$field] = $request->file($field)->store($folder, 'uploads');
            }
        }

        return $data;
    }

    /**
     * @param  array<int, string>  $fields
     */
    protected function deleteUploads(Model $model, array $fields): void
    {
        foreach ($fields as $field) {
            if ($model->{$field}) {
                Storage::disk('uploads')->delete($model->{$field});
            }
        }
    }
}
