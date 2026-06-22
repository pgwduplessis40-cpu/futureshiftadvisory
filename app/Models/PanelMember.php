<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PanelMember extends Model
{
    use HasUuids;

    public const TYPE_BROKER = 'broker';

    public const TYPE_COACH = 'coach';

    public const STATUS_INVITED = 'invited';

    public const STATUS_APPLICATION_PENDING = 'application_pending';

    public const STATUS_INFORMATION_REQUESTED = 'information_requested';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_APPROVED_PENDING_AGREEMENT = 'approved_pending_agreement';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const FSP_STATUS_CURRENT = 'current';

    public const FSP_STATUS_LAPSED = 'lapsed';

    public const FSP_STATUS_UNKNOWN = 'unknown';

    protected $guarded = [];

    protected $casts = [
        'application' => 'array',
        'coach_profile' => 'array',
        'coach_specialisations' => 'array',
        'coach_vetting' => 'array',
        'applied_at' => 'datetime',
        'approved_at' => 'datetime',
        'coach_vetted_at' => 'datetime',
        'fsp_last_checked_at' => 'datetime',
        'professional_memberships' => 'array',
        'suspended_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public static function panelTypes(): array
    {
        return [self::TYPE_BROKER, self::TYPE_COACH];
    }

    /**
     * @return BelongsTo<User, PanelMember>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<InviteToken, PanelMember>
     */
    public function inviteToken(): BelongsTo
    {
        return $this->belongsTo(InviteToken::class);
    }

    /**
     * @return HasMany<PanelAgreement>
     */
    public function agreements(): HasMany
    {
        return $this->hasMany(PanelAgreement::class);
    }

    /**
     * @return HasMany<Referral>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    /**
     * @return HasMany<ReverseReferral>
     */
    public function reverseReferrals(): HasMany
    {
        return $this->hasMany(ReverseReferral::class);
    }
}
