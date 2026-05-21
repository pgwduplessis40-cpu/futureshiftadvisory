<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'email',
    'phone',
    'company',
    'engagement_interest',
    'message',
    'source',
    'ip_address',
    'user_agent',
    'status',
    'assigned_advisor_user_id',
    'dedupe_key',
    'payload_hash',
    'intake_payload',
    'triage_outcome',
    'triage_notes',
    'triaged_at',
    'triaged_by_user_id',
    'invite_token_id',
])]
class ProspectLead extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_INVITED = 'invited';

    public const STATUS_PARKED = 'parked';

    public const STATUS_DECLINED = 'declined';

    protected $table = 'prospect_leads';

    protected $casts = [
        'intake_payload' => 'array',
        'triaged_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, ProspectLead>
     */
    public function assignedAdvisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_advisor_user_id');
    }

    /**
     * @return BelongsTo<User, ProspectLead>
     */
    public function triagedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triaged_by_user_id');
    }

    /**
     * @return BelongsTo<InviteToken, ProspectLead>
     */
    public function inviteToken(): BelongsTo
    {
        return $this->belongsTo(InviteToken::class);
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_INVITED,
            self::STATUS_PARKED,
            self::STATUS_DECLINED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function triageOutcomes(): array
    {
        return [
            self::STATUS_INVITED,
            self::STATUS_PARKED,
            self::STATUS_DECLINED,
        ];
    }
}
