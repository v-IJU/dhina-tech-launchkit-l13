<?php

namespace App\Models;

use App\Core\Tenancy\Tenancy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $status
 * @property bool $force_password_reset
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $last_login_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    public const STATUSES = ['active', 'inactive', 'suspended'];

    protected $fillable = [
        'name', 'first_name', 'last_name', 'username', 'email', 'password', 'phone', 'avatar',
        'status', 'job_title', 'department', 'date_of_birth', 'gender', 'address', 'city', 'state',
        'country', 'postal_code', 'bio', 'notes', 'require_two_factor', 'force_password_reset', 'theme',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'require_two_factor' => 'boolean',
            'force_password_reset' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'email', 'username', 'status', 'phone', 'job_title', 'department'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn () => $this->avatar ? Storage::disk('uploads')->url($this->avatar) : null);
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn () => initials($this->name));
    }

    /**
     * Private channel for live notifications (SaaS mode adds "tenant.{id}." in front, routes/channels.php).
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return Tenancy::channelPrefix().'App.Models.User.'.$this->getKey();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('starter.super_admin_role'));
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn ($q) => $q->where(fn ($q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->orWhere('username', 'like', "%{$term}%")));
    }
}
