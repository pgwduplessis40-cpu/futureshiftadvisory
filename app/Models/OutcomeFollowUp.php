<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OutcomeFollowUp extends Model
{
    use HasUuids;

    public const SUBJECT_ENTREPRENEUR = 'entrepreneur';

    public const SUBJECT_DUE_DILIGENCE = 'due_diligence';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'cadence_month' => 'integer',
        'engagement_completed_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'response_payload' => 'array',
        'outcome_signal' => 'array',
    ];

    /**
     * @return BelongsTo<Client, OutcomeFollowUp>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<EntrepreneurProfile, OutcomeFollowUp>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<PlanAssessment, OutcomeFollowUp>
     */
    public function planAssessment(): BelongsTo
    {
        return $this->belongsTo(PlanAssessment::class);
    }

    /**
     * @return BelongsTo<DdEngagement, OutcomeFollowUp>
     */
    public function ddEngagement(): BelongsTo
    {
        return $this->belongsTo(DdEngagement::class);
    }

    /**
     * @return BelongsTo<ServiceActivation, OutcomeFollowUp>
     */
    public function serviceActivation(): BelongsTo
    {
        return $this->belongsTo(ServiceActivation::class);
    }

    /**
     * @return BelongsTo<ConversionOutcome, OutcomeFollowUp>
     */
    public function conversionOutcome(): BelongsTo
    {
        return $this->belongsTo(ConversionOutcome::class);
    }

    /**
     * @return BelongsTo<DdOutcomeRecord, OutcomeFollowUp>
     */
    public function ddOutcomeRecord(): BelongsTo
    {
        return $this->belongsTo(DdOutcomeRecord::class);
    }

    /**
     * @return BelongsTo<User, OutcomeFollowUp>
     */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }
}
