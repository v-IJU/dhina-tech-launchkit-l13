<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved delivery / billing address. One per customer can be the default.
 *
 * @property int $id
 * @property int $customer_id
 * @property string|null $label
 * @property string $name
 * @property string|null $phone
 * @property string $line1
 * @property string|null $line2
 * @property string $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string $country
 * @property bool $is_default
 */
class CustomerAddress extends Model
{
    public const FIELDS = ['label', 'name', 'phone', 'line1', 'line2', 'city', 'state', 'postal_code', 'country', 'is_default'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    protected static function booted(): void
    {
        // The first address is the default; making one default clears the others.
        static::saving(function (CustomerAddress $address) {
            if (! $address->exists && ! static::query()->where('customer_id', $address->customer_id)->exists()) {
                $address->is_default = true;
            }
        });
        static::saved(function (CustomerAddress $address) {
            if ($address->is_default) {
                static::query()->where('customer_id', $address->customer_id)->whereKeyNot($address->id)->update(['is_default' => false]);
            }
        });
        static::deleted(function (CustomerAddress $address) {
            if ($address->is_default) {
                static::query()->where('customer_id', $address->customer_id)->oldest('id')->first()?->update(['is_default' => true]);
            }
        });
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * One-line address for lists: "12 MG Road, Chennai 600001, India".
     */
    public function oneLine(): string
    {
        return collect([$this->line1, $this->line2, trim($this->city.' '.$this->postal_code), $this->state, $this->country])
            ->filter()->implode(', ');
    }

    /**
     * Validation rules shared by the website, the API and the admin panel.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'is_default' => ['boolean'],
        ];
    }
}
