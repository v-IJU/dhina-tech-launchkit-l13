<?php

declare(strict_types=1);

namespace App\Core\Import;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $row
 * @property list<string> $messages
 * @property array<string, mixed> $values
 */
class ImportRunError extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['messages' => 'array', 'values' => 'array'];
    }
}
