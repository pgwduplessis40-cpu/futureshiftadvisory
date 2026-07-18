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
        'pilot_fee_waiver_enabled' => 'boolean',
        'pilot_fee_waiver_starts_at' => 'datetime',
        'pilot_fee_waiver_expires_at' => 'datetime',
        'pilot_fee_waiver_approved_at' => 'datetime',
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
     * @return BelongsTo<User, Client>
     */
    public function pilotFeeWaiverApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pilot_fee_waiver_approved_by_user_id');
    }

    /**
     * @return HasMany<ClientTeamMember>
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(ClientTeamMember::class);
    }

    /**
     * @return HasMany<AdvisorClientTransferRequest>
     */
    public function advisorTransferRequests(): HasMany
    {
        return $this->hasMany(AdvisorClientTransferRequest::class);
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
     * @return HasMany<CoachReferralSuggestion>
     */
    public function coachReferralSuggestions(): HasMany
    {
        return $this->hasMany(CoachReferralSuggestion::class);
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
     * @return HasMany<IntegrationScope>
     */
    public function integrationScopes(): HasMany
    {
        return $this->hasMany(IntegrationScope::class);
    }

    /**
     * @return HasMany<Proposal>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /**
     * @return HasMany<StrategicBudget>
     */
    public function strategicBudgets(): HasMany
    {
        return $this->hasMany(StrategicBudget::class);
    }

    /**
     * @return HasMany<PaymentAuthority>
     */
    public function paymentAuthorities(): HasMany
    {
        return $this->hasMany(PaymentAuthority::class);
    }

    /**
     * @return HasMany<PaymentSchedule>
     */
    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    /**
     * @return HasMany<Payment>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<AccountingInvoiceBatch>
     */
    public function accountingInvoiceBatches(): HasMany
    {
        return $this->hasMany(AccountingInvoiceBatch::class);
    }

    /**
     * @return HasMany<AccountingInvoice>
     */
    public function accountingInvoices(): HasMany
    {
        return $this->hasMany(AccountingInvoice::class);
    }

    /**
     * @return HasMany<BusinessHealthSnapshot>
     */
    public function businessHealthSnapshots(): HasMany
    {
        return $this->hasMany(BusinessHealthSnapshot::class);
    }

    /**
     * @return HasMany<Receipt>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * @return HasMany<Referral>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    /**
     * @return HasMany<DdEngagement>
     */
    public function ddEngagements(): HasMany
    {
        return $this->hasMany(DdEngagement::class);
    }

    /**
     * @return HasMany<ServiceActivation>
     */
    public function serviceActivations(): HasMany
    {
        return $this->hasMany(ServiceActivation::class);
    }

    /**
     * @return HasMany<NpoEngagement>
     */
    public function npoEngagements(): HasMany
    {
        return $this->hasMany(NpoEngagement::class);
    }

    /**
     * @return HasMany<NpoComplianceAlert>
     */
    public function npoComplianceAlerts(): HasMany
    {
        return $this->hasMany(NpoComplianceAlert::class);
    }

    /**
     * @return HasMany<ClientFunderRecord>
     */
    public function funderRecords(): HasMany
    {
        return $this->hasMany(ClientFunderRecord::class);
    }

    /**
     * @return HasMany<ClientFunderAlert>
     */
    public function funderAlerts(): HasMany
    {
        return $this->hasMany(ClientFunderAlert::class);
    }

    /**
     * @return HasMany<NpoValueCalculation>
     */
    public function npoValueCalculations(): HasMany
    {
        return $this->hasMany(NpoValueCalculation::class);
    }

    /**
     * @return HasMany<NpoSocialEnterpriseScorecard>
     */
    public function npoSocialEnterpriseScorecards(): HasMany
    {
        return $this->hasMany(NpoSocialEnterpriseScorecard::class);
    }

    /**
     * @return HasMany<NpoTensionAnalysis>
     */
    public function npoTensionAnalyses(): HasMany
    {
        return $this->hasMany(NpoTensionAnalysis::class);
    }

    /**
     * @return HasMany<GovernanceReviewFinding>
     */
    public function governanceReviewFindings(): HasMany
    {
        return $this->hasMany(GovernanceReviewFinding::class);
    }

    /**
     * @return HasMany<CoachReferralAuthorisation>
     */
    public function coachReferralAuthorisations(): HasMany
    {
        return $this->hasMany(CoachReferralAuthorisation::class);
    }

    /**
     * @return HasMany<Report>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * @return HasMany<WebsiteUrlConfirmation>
     */
    public function websiteUrlConfirmations(): HasMany
    {
        return $this->hasMany(WebsiteUrlConfirmation::class);
    }

    /**
     * @return HasMany<WebsiteAuditSnapshot>
     */
    public function websiteAuditSnapshots(): HasMany
    {
        return $this->hasMany(WebsiteAuditSnapshot::class);
    }

    /**
     * @return HasMany<Testimonial>
     */
    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    /**
     * @return HasMany<VoiceNote>
     */
    public function voiceNotes(): HasMany
    {
        return $this->hasMany(VoiceNote::class);
    }

    /**
     * @return HasMany<VoiceAssistantSession>
     */
    public function voiceAssistantSessions(): HasMany
    {
        return $this->hasMany(VoiceAssistantSession::class);
    }

    /**
     * @return HasMany<CallLog>
     */
    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class);
    }

    /**
     * @return HasMany<NzToolConnection>
     */
    public function nzToolConnections(): HasMany
    {
        return $this->hasMany(NzToolConnection::class);
    }

    /**
     * @return HasMany<Meeting>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    /**
     * @return HasMany<ClientLeavePeriod>
     */
    public function leavePeriods(): HasMany
    {
        return $this->hasMany(ClientLeavePeriod::class);
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
