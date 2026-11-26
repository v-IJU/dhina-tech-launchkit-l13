<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passkeys\Passkey;

/**
 * A customer's passkey (table customer_passkeys). Admin users' passkeys are Laravel\Passkeys\Passkey.
 */
class CustomerPasskey extends Passkey
{
    protected $table = 'customer_passkeys';

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }
}
