<?php

declare(strict_types=1);

namespace App\Core\Import;

/**
 * Outcome of a CsvImport run, shown on the import page.
 */
class ImportResult
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public bool $dryRun = false;

    /** Problem with the whole file (wrong headings, too many rows…). */
    public ?string $fatal = null;

    /** @var array<int, string> */
    public array $ignoredColumns = [];

    /** @var array<int, array{line: int, messages: array<int, string>, values: array<string, string>}> */
    public array $errors = [];

    /**
     * @param  array<int, string>  $messages
     * @param  array<string, string>  $values
     */
    public function addError(int $line, array $messages, array $values): void
    {
        $this->errors[] = ['line' => $line, 'messages' => $messages, 'values' => $values];
    }

    public function failed(): int
    {
        return count($this->errors);
    }

    public function ok(): bool
    {
        return $this->fatal === null && $this->errors === [];
    }

    public function summary(): string
    {
        if ($this->fatal) {
            return $this->fatal;
        }

        $verb = $this->dryRun ? ' would be' : '';

        return sprintf('%d%s created, %d%s updated, %d skipped (empty), %d with errors.',
            $this->created, $verb, $this->updated, $verb, $this->skipped, $this->failed());
    }
}
