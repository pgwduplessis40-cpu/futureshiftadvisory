<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class BusinessPlan extends Model
{
    use HasUuids;

    public const SOURCE_DUE_DILIGENCE = 'due_diligence';

    public const SOURCE_ENTREPRENEUR = 'entrepreneur';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_BUILDING = 'building';

    public const STATUS_READY = 'ready';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ASSESSING = 'assessing';

    public const STATUS_REVISING = 'revising';

    public const STATUS_FINALISED = 'finalised';

    public const STATUS_LAUNCHED = 'launched';

    public const STATUS_FOUNDING = 'founding';

    protected $guarded = [];

    protected $casts = [
        'current_phase' => 'integer',
        'founding_advisory_payload' => 'array',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'living_plan_next_update_at' => 'datetime',
        'living_plan_last_prompted_at' => 'datetime',
        'living_plan_last_assessed_at' => 'datetime',
        'living_plan_divergence_flags' => 'array',
    ];

    /**
     * @return BelongsTo<Client, BusinessPlan>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<EntrepreneurProfile, BusinessPlan>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<DdEngagement, BusinessPlan>
     */
    public function ddEngagement(): BelongsTo
    {
        return $this->belongsTo(DdEngagement::class);
    }

    /**
     * @return BelongsTo<User, BusinessPlan>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<PlanPhase>
     */
    public function phases(): HasMany
    {
        return $this->hasMany(PlanPhase::class);
    }

    /**
     * @return HasMany<PlanSection>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PlanSection::class);
    }

    /**
     * @return HasOne<EntrepreneurBudget>
     */
    public function budgetRunway(): HasOne
    {
        return $this->hasOne(EntrepreneurBudget::class);
    }

    /**
     * @return HasMany<PlanAssessment>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(PlanAssessment::class);
    }

    /**
     * @return HasMany<PlanRevision>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PlanRevision::class);
    }
}
