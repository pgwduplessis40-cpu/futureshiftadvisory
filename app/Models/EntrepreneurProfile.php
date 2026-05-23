<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntrepreneurStage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EntrepreneurProfile extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'stage' => EntrepreneurStage::class,
    ];

    /**
     * @return BelongsTo<User, EntrepreneurProfile>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, EntrepreneurProfile>
     */
    public function assignedAdvisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_advisor_id');
    }

    /**
     * @return BelongsTo<InviteToken, EntrepreneurProfile>
     */
    public function inviteToken(): BelongsTo
    {
        return $this->belongsTo(InviteToken::class);
    }

    /**
     * @return HasMany<ReadinessAssessment>
     */
    public function readinessAssessments(): HasMany
    {
        return $this->hasMany(ReadinessAssessment::class);
    }

    /**
     * @return HasMany<IdeaValidation>
     */
    public function ideaValidations(): HasMany
    {
        return $this->hasMany(IdeaValidation::class);
    }

    /**
     * @return HasMany<BusinessPlan>
     */
    public function businessPlans(): HasMany
    {
        return $this->hasMany(BusinessPlan::class);
    }

    /**
     * @return HasMany<AdvisoryReadinessSignal>
     */
    public function advisoryReadinessSignals(): HasMany
    {
        return $this->hasMany(AdvisoryReadinessSignal::class);
    }
}
