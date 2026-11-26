<?php

declare(strict_types=1);

namespace App\Core\Export;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Quick export from a column list, for list pages that don't need their own export class:
 *   ->export('activity', ['When' => 'created_at', 'User' => 'causer.name', 'Action' => fn ($row) => …])
 */
class TableExport extends ModuleExport
{
    /**
     * @param  Builder<covariant Model>  $query
     * @param  array<string, string|Closure>  $columns  heading => attribute (dot notation ok) or closure($row)
     */
    public function __construct(Builder $query, private array $columns)
    {
        parent::__construct($query);
    }

    public function headings(): array
    {
        return array_keys($this->columns);
    }

    public function map($row): array
    {
        return array_map(function ($column) use ($row) {
            $value = $column instanceof Closure ? $column($row) : data_get($row, $column);

            return match (true) {
                $value instanceof \DateTimeInterface => $this->date($value),
                is_bool($value) => $this->yesNo($value),
                $value instanceof \BackedEnum => $value->value,
                is_array($value) => implode(', ', $value),
                default => $value,
            };
        }, array_values($this->columns));
    }
}
