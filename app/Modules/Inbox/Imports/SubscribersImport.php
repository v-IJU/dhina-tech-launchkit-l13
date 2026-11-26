<?php

declare(strict_types=1);

namespace App\Modules\Inbox\Imports;

use App\Core\Import\ChunkImport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Subscribers → Import (Excel or CSV). Example of a CHUNK import: mailing lists are often 50,000+ emails,
 * so instead of 2 queries per row this does 2 queries per chunk:
 *   1. clean + check every email of the chunk (bad or duplicate rows are skipped with the reason)
 *   2. one SELECT … WHERE email IN (…) to find who is already subscribed
 *   3. one INSERT for all new emails
 * Known emails count as "updated" (nothing to change). See App\Core\Import\ChunkImport.
 */
class SubscribersImport extends ChunkImport
{
    /** Columns of the file (headings) with an example, for the template and the help table. */
    public function columns(): array
    {
        return [
            'email' => 'reader@example.com',
        ];
    }

    /** Shown in the help table on the import page. */
    protected function helpRules(): array
    {
        return ['email' => ['required', 'email', 'max:255']];
    }

    public function importChunk(Collection $rows): void
    {
        // 1. Clean and check each row; keep the first row of each email.
        $emails = [];   // email => row number
        foreach ($rows as $line => $row) {
            $email = strtolower((string) $row->get('email'));

            if ($email === '') {
                $this->skip($line, 'The email is empty.', $row);
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
                $this->skip($line, "\"{$email}\" is not a valid email address.", $row);
            } elseif (isset($emails[$email])) {
                $this->skip($line, "Same email as row {$emails[$email]}.", $row);
            } else {
                $emails[$email] = $line;
            }
        }

        foreach (array_chunk(array_keys($emails), 500) as $batch) {
            // 2. Who is already subscribed? One query for 500 emails.
            $known = DB::table('newsletter_subscribers')->whereIn('email', $batch)->pluck('email')->map(fn ($e) => strtolower($e))->all();
            $new = array_values(array_diff($batch, $known));

            // 3. Add the new ones in one insert (insertOrIgnore: safe if someone subscribes meanwhile).
            $now = now();
            DB::table('newsletter_subscribers')->insertOrIgnore(array_map(fn ($email) => ['email' => $email, 'created_at' => $now, 'updated_at' => $now], $new));

            $this->created(count($new));
            $this->updated(count($known));
        }
    }
}
