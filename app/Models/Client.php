<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class Client extends Model
{
    use HasUuids;

    public const DATA_QUALITY_HIGH = 'high';

    public const DATA_QUALITY_MEDIUM = 'medium';

    public const DATA_QUALITY_LOW = 'low';

    public const DATA_QUALITY_INSUFFICIENT = 'insufficient';

    protected $guarded = [];

    protected $casts = [
        'engagement_type' => EngagementType::class,
        'status' => ClientStatus::class,
        'address' => 'array',
        'gst_registered' => 'boolean',
        'directors' => 'array',
        'registry_sources' => 'array',
        'onboarding_wizard_state' => 'array',
        'engagement_type_locked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, Client>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, Client>
     */
    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_contact_user_id');
    }

    /**
     * @return HasMany<ClientTeamMember>
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(ClientTeamMember::class);
    }

    /**
     * @return HasMany<ConflictDeclaration>
     */
    public function conflictDeclarations(): HasMany
    {
        return $this->hasMany(ConflictDeclaration::class);
    }

    /**
     * @return HasMany<WellbeingCheckin>
     */
    public function wellbeingCheckins(): HasMany
    {
        return $this->hasMany(WellbeingCheckin::class);
    }

    /**
     * @return HasMany<CoachingSignal>
     */
    public function coachingSignals(): HasMany
    {
        return $this->hasMany(CoachingSignal::class);
    }

    /**
     * @return HasMany<OffboardingRecord>
     */
    public function offboardingRecords(): HasMany
    {
        return $this->hasMany(OffboardingRecord::class);
    }

    /**
     * @return HasMany<MessageThread>
     */
    public function messageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class);
    }

    /**
     * @return HasMany<KnowledgeEntry>
     */
    public function knowledgeEntries(): HasMany
    {
        return $this->hasMany(KnowledgeEntry::class);
    }

    /**
     * @return HasMany<KnowledgeAssessment>
     */
    public function knowledgeAssessments(): HasMany
    {
        return $this->hasMany(KnowledgeAssessment::class);
    }

    /**
     * @return HasMany<Goal>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * @return HasMany<Milestone>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    /**
     * @return HasMany<Scenario>
     */
    public function scenarios(): HasMany
    {
        return $this->hasMany(Scenario::class);
    }

    /**
     * @return HasMany<SuccessionPlan>
     */
    public function successionPlans(): HasMany
    {
        return $this->hasMany(SuccessionPlan::class);
    }

    /**
     * @return HasMany<FeeCalculation>
     */
    public function feeCalculations(): HasMany
    {
        return $this->hasMany(FeeCalculation::class);
    }

    /**
     * @return HasMany<Proposal>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /**
     * @return HasMany<Report>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * @return HasMany<Meeting>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    /**
     * @return HasMany<IndustryBriefing>
     */
    public function industryBriefings(): HasMany
    {
        return $this->hasMany(IndustryBriefing::class);
    }

    /**
     * @return HasMany<PreMeetingBrief>
     */
    public function preMeetingBriefs(): HasMany
    {
        return $this->hasMany(PreMeetingBrief::class);
    }

    public function engagementTypeIsLocked(): bool
    {
        if ($this->engagement_type_locked_at !== null) {
            return true;
        }

        if (! $this->exists || ! Schema::hasTable('questionnaire_responses')) {
            return false;
        }

        return DB::table('questionnaire_responses')
            ->where('client_id', $this->getKey())
            ->exists();
    }
}
