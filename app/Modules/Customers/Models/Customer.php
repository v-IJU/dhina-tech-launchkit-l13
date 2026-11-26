<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Models\LoginEvent;
use App\Modules\Customers\Database\Factories\CustomerFactory;
use App\Modules\Customers\Notifications\CustomerResetPassword;
use App\Modules\Customers\Notifications\CustomerVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A website / app customer. Signs in with the "customer" guard (website) or a
 * Sanctum token (mobile app, /api/v1/shop). Never has roles or admin access.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $status
 * @property Carbon|null $date_of_birth
 * @property string|null $gender
 * @property string|null $locale
 * @property string|null $avatar
 * @property string|null $avatar_url
 * @property bool $marketing_opt_in
 * @property string|null $notes
 * @property Carbon|null $email_verified_at
 * @property string|null $two_factor_secret
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 * @property-read CustomerAddress|null $defaultAddress
 */
class Customer extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<CustomerFactory> */
    use HasApiTokens, HasFactory, LogsActivity, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    public const STATUSES = ['active' => 'Active', 'blocked' => 'Blocked'];

    public const GENDERS = ['female' => 'Female', 'male' => 'Male', 'other' => 'Other'];

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'status', 'date_of_birth', 'gender',
        'locale', 'avatar', 'marketing_opt_in', 'notes',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'notes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'date_of_birth' => 'date',
            'marketing_opt_in' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    protected static function booted(): void
    {
        static::forceDeleted(function (Customer $customer) {
            $customer->tokens()->delete();
            if ($customer->avatar) {
                Storage::disk('uploads')->delete($customer->avatar);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('customers')
            ->logOnly(['name', 'email', 'phone', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->orderByDesc('is_default')->orderBy('id');
    }

    /**
     * @return HasOne<CustomerAddress, $this>
     */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', true);
    }

    /**
     * @return HasMany<LoginEvent, $this>
     */
    public function loginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class)->latest('created_at');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Must this customer verify their email before using the account?
     * (Settings > Customers > "Customers must verify their email".)
     */
    public function needsVerification(): bool
    {
        return (bool) setting('customer_verify_email', true) && ! $this->hasVerifiedEmail();
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%"));
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new CustomerResetPassword($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CustomerVerifyEmail);
    }

    /**
     * Email is stored lower-case so sign-in is case-insensitive.
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value) => strtolower(trim($value)));
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn () => $this->avatar ? Storage::disk('uploads')->url($this->avatar) : null);
    }

    /**
     * Customers keep their passkeys in customer_passkeys (admin users: passkeys).
     *
     * @return HasMany<CustomerPasskey, $this>
     */
    public function passkeys(): HasMany
    {
        return $this->hasMany(CustomerPasskey::class, 'user_id');
    }
}
