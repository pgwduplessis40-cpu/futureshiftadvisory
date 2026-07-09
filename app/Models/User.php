<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ClientStatus;
use App\Notifications\Auth\PasswordResetLinkNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\IntegrationActivationResolver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Throwable;

#[Fillable([
    'name',
    'email',
    'mobile_phone',
    'password',
    'user_type',
    'primary_role',
    'mfa_enabled_at',
    'mfa_method',
    'last_password_set_at',
    'session_timeout_minutes',
    'suspended_at',
    'suspended_reason',
    'deactivation_requested_at',
    'deactivation_requested_reason',
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

    public const TYPE_NPO_BOARD_MEMBER = 'npo_board_member';

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
            'deactivation_requested_at' => 'datetime',
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
     * @return HasMany<DeviceRegistration>
     */
    public function deviceRegistrations(): HasMany
    {
        return $this->hasMany(DeviceRegistration::class);
    }

    /**
     * @return HasOne<CommunicationPreference>
     */
    public function communicationPreference(): HasOne
    {
        return $this->hasOne(CommunicationPreference::class);
    }

    /**
     * @return HasMany<EntrepreneurProfile>
     */
    public function assignedEntrepreneurProfiles(): HasMany
    {
        return $this->hasMany(EntrepreneurProfile::class, 'assigned_advisor_id');
    }

    /**
     * @return HasMany<AdvisorTeam>
     */
    public function ledAdvisorTeams(): HasMany
    {
        return $this->hasMany(AdvisorTeam::class, 'lead_advisor_user_id');
    }

    /**
     * @return HasMany<AdvisorTeamMember>
     */
    public function advisorTeamMemberships(): HasMany
    {
        return $this->hasMany(AdvisorTeamMember::class);
    }

    /**
     * @return HasOne<EntrepreneurProfile>
     */
    public function entrepreneurProfile(): HasOne
    {
        return $this->hasOne(EntrepreneurProfile::class);
    }

    /**
     * @return HasOne<PanelMember>
     */
    public function panelMember(): HasOne
    {
        return $this->hasOne(PanelMember::class);
    }

    /**
     * @return HasMany<MessageThreadParticipant>
     */
    public function messageThreadParticipants(): HasMany
    {
        return $this->hasMany(MessageThreadParticipant::class);
    }

    /**
     * @return HasMany<Message>
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_user_id');
    }

    /**
     * @return HasMany<KnowledgeEntry>
     */
    public function knowledgeEntries(): HasMany
    {
        return $this->hasMany(KnowledgeEntry::class, 'author_user_id');
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
        if (! Schema::hasTable('client_team')) {
            return [];
        }

        if ($this->isNpoBoardMember()) {
            return [];
        }

        $query = DB::table('client_team')
            ->where('client_team.user_id', $this->getKey());

        if (
            Schema::hasTable('clients')
            && Schema::hasColumn('clients', 'status')
            && in_array($this->user_type, [self::TYPE_CLIENT_PRIMARY, self::TYPE_CLIENT_TEAM], true)
        ) {
            $query
                ->join('clients', 'clients.id', '=', 'client_team.client_id')
                ->where('clients.status', '!=', ClientStatus::SUSPENDED->value);
        }

        $ids = $query
            ->pluck('client_team.client_id')
            ->all();

        if (
            Schema::hasTable('advisor_teams')
            && Schema::hasTable('advisor_team_members')
            && Schema::hasColumn('client_team', 'advisor_team_id')
        ) {
            $leadTeamIds = DB::table('advisor_teams')
                ->where('lead_advisor_user_id', $this->getKey())
                ->pluck('id')
                ->all();

            $membershipLeadTeamIds = DB::table('advisor_team_members')
                ->where('user_id', $this->getKey())
                ->where('role', AdvisorTeamMember::ROLE_LEAD)
                ->whereNull('left_at')
                ->pluck('advisor_team_id')
                ->all();

            $teamIds = array_values(array_unique(array_map('strval', array_merge($leadTeamIds, $membershipLeadTeamIds))));

            if ($teamIds !== []) {
                $teamClientIds = DB::table('client_team')
                    ->whereIn('advisor_team_id', $teamIds)
                    ->pluck('client_id')
                    ->all();

                $ids = array_merge($ids, $teamClientIds);
            }
        }

        return array_values(array_unique(array_map('strval', $ids)));
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
            self::TYPE_NPO_BOARD_MEMBER,
        ];
    }

    public function isNpoBoardMember(): bool
    {
        if ($this->user_type === self::TYPE_NPO_BOARD_MEMBER || $this->primary_role === self::TYPE_NPO_BOARD_MEMBER) {
            return true;
        }

        return Schema::hasTable(config('permission.table_names.roles', 'roles'))
            && Schema::hasTable(config('permission.table_names.model_has_roles', 'model_has_roles'))
            && $this->hasRole(self::TYPE_NPO_BOARD_MEMBER);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        try {
            $this->notify(new PasswordResetLinkNotification($token));
            $this->recordPasswordResetAudit('auth.password_reset_link_sent');
        } catch (Throwable $exception) {
            $this->recordPasswordResetAudit('auth.password_reset_link_failed', [
                'exception' => $exception::class,
                'message' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            throw ValidationException::withMessages([
                'email' => 'We could not send the password reset email right now. Please contact support and we will help you regain access.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function recordPasswordResetAudit(string $action, array $extra = []): void
    {
        try {
            if (! Schema::hasTable('audit_events')) {
                return;
            }

            app(AuditWriter::class)->record(
                action: $action,
                subject: $this,
                actor: null,
                after: array_merge([
                    'email_hash' => hash('sha256', Str::lower(trim((string) $this->getEmailForPasswordReset()))),
                    'mailer' => (string) config('mail.default', 'log'),
                    'mail_from_configured' => filter_var((string) config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false,
                    'mail_delivery_ready' => app(IntegrationActivationResolver::class)->readiness('mail_delivery'),
                ], $extra),
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
