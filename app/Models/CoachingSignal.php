<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CoachingSignal extends Model
{
    use HasUuids;

    public const TYPE_LOW_PERSONAL_COPING_STREAK = 'low_personal_coping_streak';

    public const TYPE_LEADERSHIP_CAPABILITY_GAP = 'leadership_capability_gap';

    public const TYPE_OWNER_READINESS_PRIMARY_CONSTRAINT = 'owner_readiness_primary_constraint';

    public const TYPE_FINANCIAL_STRESS = 'financial_stress';

    public const TYPE_CAREER_TRANSITION = 'career_transition';

    public const TYPE_ENTREPRENEUR_PERSONAL_BARRIER = 'entrepreneur_personal_barrier';

    protected $guarded = [];

    protected $casts = [
        'evidence' => 'array',
        'generated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, CoachingSignal>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<EntrepreneurProfile, CoachingSignal>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<User, CoachingSignal>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<WellbeingCheckin, CoachingSignal>
     */
    public function triggerCheckin(): BelongsTo
    {
        return $this->belongsTo(WellbeingCheckin::class, 'trigger_checkin_id');
    }
}
