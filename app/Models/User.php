<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'password',
    'user_type',
    'primary_role',
    'mfa_enabled_at',
    'mfa_method',
    'last_password_set_at',
    'session_timeout_minutes',
    'suspended_at',
    'suspended_reason',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    public const TYPE_SUPER_ADMIN = 'super_admin';

    public const TYPE_ADVISOR = 'advisor';

    public const TYPE_JUNIOR_ADVISOR = 'junior_advisor';

    public const TYPE_ENTREPRENEUR_MENTOR = 'entrepreneur_mentor';

    public const TYPE_CLIENT_PRIMARY = 'client_primary';

    public const TYPE_CLIENT_TEAM = 'client_team';

    public const TYPE_ENTREPRENEUR = 'entrepreneur';

    public const TYPE_BROKER = 'broker';

    public const TYPE_COACH = 'coach';

    public const MFA_METHOD_TOTP = 'totp';

    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'mfa_enabled_at' => 'datetime',
            'last_password_set_at' => 'datetime',
            'session_timeout_minutes' => 'integer',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MfaFactor>
     */
    public function mfaFactors(): HasMany
    {
        return $this->hasMany(MfaFactor::class);
    }

    /**
     * @return HasOne<CommunicationPreference>
     */
    public function communicationPreference(): HasOne
    {
        return $this->hasOne(CommunicationPreference::class);
    }

    public function fsaRole(): string
    {
        if (
            Schema::hasTable(config('permission.table_names.roles', 'roles'))
            && Schema::hasTable(config('permission.table_names.model_has_roles', 'model_has_roles'))
        ) {
            $role = $this->getRoleNames()->first();

            if (is_string($role) && $role !== '') {
                return $role;
            }
        }

        return $this->primary_role ?: ($this->user_type ?: 'authenticated');
    }

    /**
     * @return array<int, string>
     */
    public function accessibleClientIds(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public static function userTypes(): array
    {
        return [
            self::TYPE_SUPER_ADMIN,
            self::TYPE_ADVISOR,
            self::TYPE_JUNIOR_ADVISOR,
            self::TYPE_ENTREPRENEUR_MENTOR,
            self::TYPE_CLIENT_PRIMARY,
            self::TYPE_CLIENT_TEAM,
            self::TYPE_ENTREPRENEUR,
            self::TYPE_BROKER,
            self::TYPE_COACH,
        ];
    }
}
