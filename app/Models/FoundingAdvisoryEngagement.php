<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 */
final class FoundingAdvisoryEngagement extends Model
{
    use HasUuids;

    public const STATUS_ADVISORY_READY = 'advisory_ready';

    public const STATUS_PROPOSAL_DRAFT = 'proposal_draft';

    public const STATUS_PROPOSAL_SENT = 'proposal_sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_MOBILISING = 'mobilising';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REPLAN_DUE = 'replan_due';

    public const STATUS_TRANSITION_REVIEW = 'transition_review';

    public const STATUS_TRANSITIONED = 'transitioned';

    protected $guarded = [];

    protected $casts = [
        'baseline' => 'array',
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'replan_due_at' => 'datetime',
        'transition_review_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, FoundingAdvisoryEngagement>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<EntrepreneurProfile, FoundingAdvisoryEngagement>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<BusinessPlan, FoundingAdvisoryEngagement>
     */
    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }

    /**
     * @return BelongsTo<PlanAssessment, FoundingAdvisoryEngagement>
     */
    public function planAssessment(): BelongsTo
    {
        return $this->belongsTo(PlanAssessment::class);
    }

    /**
     * @return BelongsTo<AdvisoryReadinessSignal, FoundingAdvisoryEngagement>
     */
    public function advisoryReadinessSignal(): BelongsTo
    {
        return $this->belongsTo(AdvisoryReadinessSignal::class);
    }

    /**
     * @return BelongsTo<Proposal, FoundingAdvisoryEngagement>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<User, FoundingAdvisoryEngagement>
     */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    /**
     * @return BelongsTo<User, FoundingAdvisoryEngagement>
     */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    /**
     * @return HasMany<FoundingRoadmapVersion>
     */
    public function roadmapVersions(): HasMany
    {
        return $this->hasMany(FoundingRoadmapVersion::class);
    }
}
