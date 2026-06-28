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
        'gamification_on' => 'boolean',
        'current_streak' => 'integer',
        'last_active_at' => 'datetime',
    ];

    public function currentStage(): EntrepreneurStage
    {
        return EntrepreneurStage::tryFrom($this->rawStageValue()) ?? EntrepreneurStage::ONBOARDING;
    }

    public function currentStageValue(): string
    {
        return $this->currentStage()->value;
    }

    public function currentStageLabel(): string
    {
        return $this->currentStage()->label();
    }

    public function ensureStageIsValid(?EntrepreneurStage $fallback = null): EntrepreneurStage
    {
        $stage = EntrepreneurStage::tryFrom($this->rawStageValue());
        if ($stage instanceof EntrepreneurStage) {
            return $stage;
        }

        $stage = $fallback ?? EntrepreneurStage::ONBOARDING;
        $this->forceFill(['stage' => $stage])->save();

        return $stage;
    }

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
     * @return BelongsTo<Client, EntrepreneurProfile>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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

    /**
     * @return HasMany<EntrepreneurMilestoneAward>
     */
    public function milestoneAwards(): HasMany
    {
        return $this->hasMany(EntrepreneurMilestoneAward::class);
    }

    /**
     * @return HasMany<EntrepreneurStreakEvent>
     */
    public function streakEvents(): HasMany
    {
        return $this->hasMany(EntrepreneurStreakEvent::class);
    }

    /**
     * @return HasMany<ConversionOutcome>
     */
    public function conversionOutcomes(): HasMany
    {
        return $this->hasMany(ConversionOutcome::class);
    }

    private function rawStageValue(): string
    {
        $raw = $this->getRawOriginal('stage') ?? $this->getAttributes()['stage'] ?? null;

        if ($raw instanceof EntrepreneurStage) {
            return $raw->value;
        }

        if ($raw instanceof \BackedEnum) {
            return (string) $raw->value;
        }

        $value = trim((string) $raw);

        return $value !== '' ? $value : EntrepreneurStage::ONBOARDING->value;
    }
}
