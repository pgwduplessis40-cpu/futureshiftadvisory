<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StrategicBudget extends Model
{
    use HasUuids;

    public const PATHWAY_ADVISORY = 'advisory';

    public const PATHWAY_DUE_DILIGENCE = 'due_diligence';

    public const PATHWAY_POST_ACQUISITION = 'post_acquisition';

    public const PATHWAY_NPO = 'npo';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_SYSTEM_DRAFT = 'system_draft';

    public const STATUS_CLIENT_WORKING_DRAFT = 'client_working_draft';

    public const STATUS_SUBMITTED_FOR_REVIEW = 'submitted_for_review';

    public const STATUS_ADVISOR_APPROVED = 'advisor_approved';

    public const STATUS_USED_IN_PROPOSAL = 'used_in_proposal';

    public const STATUS_ACCEPTED_PROPOSAL_SNAPSHOT = 'accepted_proposal_snapshot';

    protected $guarded = [];

    protected $casts = [
        'horizon_months' => 'integer',
        'expected_runway_months' => 'integer',
        'source_financials' => 'array',
        'client_goals' => 'array',
        'advisor_goals' => 'array',
        'business_plan_sections' => 'array',
        'business_plan_source_drafts' => 'array',
        'business_plan_prompts' => 'array',
        'assumptions' => 'array',
        'implementation_costs' => 'array',
        'monthly_fixed_costs' => 'array',
        'future_costs' => 'array',
        'revenue_forecast' => 'array',
        'funding_sources' => 'array',
        'funding_scenarios' => 'array',
        'computed' => 'array',
        'flags' => 'array',
        'confidence' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'business_plan_submitted_at' => 'datetime',
        'business_plan_approved_at' => 'datetime',
        'used_in_proposal_at' => 'datetime',
        'accepted_snapshot_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, StrategicBudget>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<BusinessPlan, StrategicBudget>
     */
    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }

    /**
     * @return BelongsTo<Proposal, StrategicBudget>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<User, StrategicBudget>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * @return BelongsTo<User, StrategicBudget>
     */
    public function businessPlanApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_plan_approved_by_user_id');
    }

    public function isUnlocked(): bool
    {
        return $this->status !== self::STATUS_LOCKED;
    }

    public function isApprovedForProposal(): bool
    {
        return in_array($this->status, [
            self::STATUS_ADVISOR_APPROVED,
            self::STATUS_USED_IN_PROPOSAL,
            self::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
        ], true);
    }
}
