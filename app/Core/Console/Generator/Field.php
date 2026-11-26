<?php

declare(strict_types=1);

namespace App\Core\Console\Generator;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * One field from --fields="name:type:modifier", e.g. "price:decimal:nullable".
 * Knows how to describe itself as a migration column, validation rule,
 * model cast and form input.
 */
final class Field
{
    public const TYPES = ['string', 'text', 'editor', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'email', 'url', 'image', 'foreignId'];

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $nullable,
        public readonly bool $unique,
    ) {}

    /**
     * @return array<int, Field>
     */
    public static function parseList(string $definition): array
    {
        $fields = [];
        foreach (array_filter(array_map('trim', explode(',', $definition))) as $part) {
            $bits = explode(':', $part);
            $name = Str::snake(trim($bits[0]));
            $type = trim($bits[1] ?? 'string');
            $mods = array_map('trim', array_slice($bits, 2));

            if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException("Invalid field name [{$bits[0]}].");
            }
            if (! in_array($type, self::TYPES, true)) {
                throw new InvalidArgumentException("Unknown type [{$type}] for [{$name}]. Use one of: ".implode(', ', self::TYPES));
            }

            $fields[] = new self($name, $type, in_array('nullable', $mods, true), in_array('unique', $mods, true));
        }

        return $fields;
    }

    public function label(): string
    {
        return Str::of($this->name)->replaceLast('_id', '')->replace('_', ' ')->ucfirst()->toString();
    }

    public function isActiveFlag(): bool
    {
        return $this->type === 'boolean' && in_array($this->name, ['is_active', 'active', 'is_published'], true);
    }

    public function relatedTable(): string
    {
        return Str::plural(Str::beforeLast($this->name, '_id'));
    }

    public function migration(): string
    {
        $line = match ($this->type) {
            'string', 'email', 'url' => "\$table->string('{$this->name}')",
            'text' => "\$table->text('{$this->name}')",
            'editor' => "\$table->longText('{$this->name}')",
            'integer' => "\$table->integer('{$this->name}')",
            'decimal' => "\$table->decimal('{$this->name}', 12, 2)",
            'boolean' => "\$table->boolean('{$this->name}')->default(".($this->isActiveFlag() ? 'true' : 'false').')',
            'date' => "\$table->date('{$this->name}')",
            'datetime' => "\$table->dateTime('{$this->name}')",
            'image' => "\$table->string('{$this->name}')->nullable()",
            'foreignId' => "\$table->foreignId('{$this->name}')->index()",
            default => "\$table->string('{$this->name}')",
        };

        if ($this->nullable && ! in_array($this->type, ['boolean', 'image'], true)) {
            $line .= '->nullable()';
        }
        if ($this->unique) {
            $line .= '->unique()';
        }

        return $line.';';
    }

    public function rules(string $modelVariable, string $table): string
    {
        $rules = [];
        $rules[] = match (true) {
            $this->type === 'boolean' => "'boolean'",
            $this->type === 'image' => "'nullable'",
            $this->nullable => "'nullable'",
            default => "'required'",
        };

        $rules = array_merge($rules, match ($this->type) {
            'string' => ["'string'", "'max:255'"],
            'text', 'editor' => ["'string'"],
            'integer' => ["'integer'"],
            'decimal' => ["'numeric'"],
            'date', 'datetime' => ["'date'"],
            'email' => ["'email'", "'max:255'"],
            'url' => ["'url'", "'max:255'"],
            'image' => ["'image'", "'max:'.config('starter.max_upload_kb')"],
            'foreignId' => ["'integer'", "'exists:{$this->relatedTable()},id'"],
            default => [],
        });

        if ($this->unique) {
            $rules[] = "Rule::unique('{$table}', '{$this->name}')->ignore(\$this->route('{$modelVariable}'))";
        }

        return "'{$this->name}' => [".implode(', ', $rules).'],';
    }

    /**
     * "->column(...)" line for the CSV importer, or null for fields that can't be imported (images).
     */
    /** Validation line for the module's Imports/{Model}Import.php rules(). */
    public function importRule(string $table): ?string
    {
        if ($this->type === 'image') {
            return null;
        }

        $rules = [match (true) {
            $this->type === 'boolean', $this->nullable => "'nullable'",
            default => "'required'",
        }];

        $rules = array_merge($rules, match ($this->type) {
            'string' => ["'string'", "'max:255'"],
            'text', 'editor' => ["'string'"],
            'integer' => ["'integer'"],
            'decimal' => ["'numeric'"],
            'boolean' => ["'boolean'"],
            'date', 'datetime' => ["'date'"],
            'email' => ["'email'", "'max:255'"],
            'url' => ["'url'", "'max:255'"],
            'foreignId' => ["'integer'", "'exists:{$this->relatedTable()},id'"],
            default => [],
        });

        if ($this->unique) {
            $rules[] = "Rule::unique('{$table}', '{$this->name}')->ignore(\$existing?->getKey())";
        }

        return "'{$this->name}' => [".implode(', ', $rules).'],';
    }

    /** Example value in the import template. */
    public function importExample(): ?string
    {
        if ($this->type === 'image') {
            return null;
        }
        $example = match ($this->type) {
            'boolean' => 'Yes',
            'integer', 'foreignId' => '1',
            'decimal' => '10.50',
            'date' => '2026-01-31',
            'datetime' => '2026-01-31 09:00:00',
            'email' => 'name@example.com',
            'url' => 'https://example.com',
            default => '',
        };

        return "'{$this->name}' => '{$example}',";
    }

    /** One value in the module's Exports/{Model}Export.php map(). */
    public function exportValue(): string
    {
        return match ($this->type) {
            'boolean' => "\$this->yesNo(\$row->{$this->name}),",
            'date', 'datetime' => "\$this->date(\$row->{$this->name}),",
            default => "\$row->{$this->name},",
        };
    }

    public function cast(): ?string
    {
        return match ($this->type) {
            'boolean' => "'{$this->name}' => 'boolean',",
            'integer', 'foreignId' => "'{$this->name}' => 'integer',",
            'decimal' => "'{$this->name}' => 'decimal:2',",
            'date' => "'{$this->name}' => 'date',",
            'datetime' => "'{$this->name}' => 'datetime',",
            default => null,
        };
    }

    /**
     * Blade markup for the create / edit form.
     */
    public function input(string $var): string
    {
        $required = ($this->nullable || in_array($this->type, ['boolean', 'image'], true)) ? '' : ' required';
        $value = ":value=\"\${$var}->{$this->name}\"";

        return match ($this->type) {
            'text' => "<x-admin.form.textarea name=\"{$this->name}\" label=\"{$this->label()}\" {$value}{$required} />",
            'editor' => "<x-admin.form.editor name=\"{$this->name}\" label=\"{$this->label()}\" {$value}{$required} />",
            'boolean' => "<x-admin.form.checkbox name=\"{$this->name}\" label=\"{$this->label()}\" :checked=\"\${$var}->{$this->name}\" />",
            'integer' => "<x-admin.form.input type=\"number\" name=\"{$this->name}\" label=\"{$this->label()}\" {$value}{$required} />",
            'decimal' => "<x-admin.form.input type=\"number\" step=\"0.01\" name=\"{$this->name}\" label=\"{$this->label()}\" {$value}{$required} />",
            'date' => "<x-admin.form.input type=\"date\" name=\"{$this->name}\" label=\"{$this->label()}\" :value=\"\${$var}->{$this->name}?->format('Y-m-d')\"{$required} />",
            'datetime' => "<x-admin.form.input type=\"datetime-local\" name=\"{$this->name}\" label=\"{$this->label()}\" :value=\"\${$var}->{$this->name}?->format('Y-m-d\\TH:i')\"{$required} />",
            'email' => "<x-admin.form.input type=\"email\" name=\"{$this->name}\" label=\"{$this->label()}\" {$value}{$required} />",
            'url' => "<x-admin.form.input type=\"url\" name=\"{$this->name}\" label=\"{$this->label()}\" {$value}{$required} />",
            'image' => "<x-admin.form.image name=\"{$this->name}\" label=\"{$this->label()}\" :url=\"\${$var}->{$this->name} ? Storage::disk('uploads')->url(\${$var}->{$this->name}) : null\" />",
            'foreignId' => "<x-admin.form.select name=\"{$this->name}\" label=\"{$this->label()}\" :options=\"\$options['{$this->name}']\" {$value} placeholder=\"Select…\"{$required} />",
            default => "<x-admin.form.input name=\"{$this->name}\" label=\"{$this->label()}\" {$value}{$required} />",
        };
    }

    /**
     * Blade markup for an index table cell.
     */
    public function cell(string $var): string
    {
        return match ($this->type) {
            'boolean' => "<x-admin.status-badge :status=\"\${$var}->{$this->name} ? 'yes' : 'no'\" />",
            'date' => "{{ \${$var}->{$this->name}?->format('d M Y') }}",
            'datetime' => "{{ \${$var}->{$this->name}?->format('d M Y H:i') }}",
            'decimal' => "{{ number_format((float) \${$var}->{$this->name}, 2) }}",
            'image' => "@if (\${$var}->{$this->name})<img src=\"{{ Storage::disk('uploads')->url(\${$var}->{$this->name}) }}\" alt=\"\" class=\"rounded\" style=\"width: 48px; height: 36px; object-fit: cover\">@endif",
            default => "{{ \\Illuminate\\Support\\Str::limit((string) \${$var}->{$this->name}, 60) }}",
        };
    }
}
