<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\IndustryBriefing;
use App\Models\IndustryIntelligenceSignal;
use App\Models\LearningUpdate;
use App\Models\NpoEngagement;
use App\Models\OffboardingRecord;
use App\Models\OperationalHealthCheckResult;
use App\Models\OperationalHealthCheckRun;
use App\Models\ProspectLead;
use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use App\Notifications\BiasMonitorSignalNotification;
use App\Notifications\ClientLifecycleNotification;
use App\Notifications\CrossClientIntelligenceNotification;
use App\Notifications\EntrepreneurDeactivationRequestedNotification;
use App\Notifications\GovernanceReviewConversionNudgeNotification;
use App\Notifications\IndustryBriefingNotification;
use App\Notifications\OffboardingCompletedNotification;
use App\Notifications\OperationalHealthAttentionNotification;
use App\Notifications\ProspectLeadReceivedNotification;
use App\Notifications\ReengagementReminderNotification;
use App\Notifications\ReferenceDataStaleNotification;
use App\Notifications\TermsDeclinedUrgentNotification;
use App\Notifications\WellbeingCheckinPromptNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

final class NotificationPayloadContractTest extends TestCase
{
    public function test_reference_data_notifications_handle_missing_and_stale_values(): void
    {
        $missing = new ReferenceDataStaleNotification($this->referenceDataTask('missing'));
        $stale = new ReferenceDataStaleNotification($this->referenceDataTask('stale'));

        $this->assertSame('reference_data.stale', $missing->databaseType());
        $this->assertMailSubject($missing->toMail($this->recipient()), 'Reference data update due');
        $this->assertSame('Industry WACC has no implemented reference-data value yet.', $missing->toArray($this->recipient())['message']);
        $this->assertSame('Industry WACC is due for review by 2026-08-31; last implemented value is 2026-07-01.', $stale->toArray($this->recipient())['message']);
    }

    public function test_wellbeing_and_prospect_notifications_preserve_client_and_lead_context(): void
    {
        $client = $this->client('Wellbeing client');
        $wellbeing = new WellbeingCheckinPromptNotification($client, '2026-08-01');
        $lead = new ProspectLead([
            'id' => 'lead-1',
            'name' => 'Prospect One',
            'email' => 'prospect@example.test',
            'company' => 'Prospect Company',
            'source' => 'website',
        ]);
        $prospect = new ProspectLeadReceivedNotification($lead);

        $this->assertSame('wellbeing.checkin.prompt', $wellbeing->databaseType());
        $this->assertMailSubject($wellbeing->toMail($this->recipient()), 'Optional monthly wellbeing check-in');
        $this->assertSame('2026-08-01', $wellbeing->toArray($this->recipient())['period_start']);
        $this->assertSame('Wellbeing client', $wellbeing->toArray($this->recipient())['client_name']);

        $this->assertSame('prospect.lead.received', $prospect->databaseType());
        $this->assertMailSubject($prospect->toMail($this->recipient()), 'New website prospect lead');
        $this->assertSame('Prospect One from Prospect Company', $prospect->toArray($this->recipient())['message']);
        $this->assertSame('website', $prospect->toArray($this->recipient())['source']);
    }

    public function test_terms_declined_notification_serializes_urgent_payload_without_accessing_a_missing_property(): void
    {
        $declinedUser = new User(['id' => 10, 'name' => 'Declined User', 'email' => 'declined@example.test']);
        $termsVersion = new TermsVersion(['id' => 'terms-1', 'version' => '2026-08']);
        $acceptance = new TermsAcceptance(['id' => 'acceptance-1']);
        $notification = new TermsDeclinedUrgentNotification($declinedUser, $termsVersion, $acceptance);

        $this->assertSame('urgent', $notification->urgency());
        $this->assertMailSubject($notification->toMail($this->recipient()), 'Urgent: Terms declined');
        $this->assertSame('urgent', $notification->toArray($this->recipient())['urgency']);
        $this->assertSame('2026-08', $notification->toArray($this->recipient())['terms_version']);
    }

    public function test_client_lifecycle_notification_preserves_reason_suspension_and_recipient_specific_routes(): void
    {
        $client = $this->client('Lifecycle client');
        $suspended = new ClientLifecycleNotification($client, ClientStatus::ACTIVE, ClientStatus::SUSPENDED, 'Compliance review');
        $active = new ClientLifecycleNotification($client, ClientStatus::PAUSED, ClientStatus::ACTIVE);
        $advisor = new User(['user_type' => User::TYPE_ADVISOR]);
        $clientUser = new User(['user_type' => User::TYPE_CLIENT_PRIMARY]);

        $this->assertMailSubject($suspended->toMail($advisor), 'Client lifecycle status updated');
        $this->assertSame('Compliance review', $suspended->toArray($advisor)['reason']);
        $this->assertNull($suspended->toArray($clientUser)['url']);
        $this->assertStringContainsString('/advisor/clients/', (string) $active->toArray($advisor)['url']);
        $this->assertStringContainsString('/portal', (string) $active->toArray($clientUser)['url']);
    }

    public function test_intelligence_and_bias_notifications_preserve_aggregate_only_metadata_and_safe_fallbacks(): void
    {
        $signal = new IndustryIntelligenceSignal([
            'id' => 'signal-1',
            'industry_code' => 'A123',
            'signal_type' => 'margin_pressure',
            'cohort_size' => 12,
            'aggregate' => ['pattern' => 'Margin pressure', 'privacy' => 'aggregate_only'],
        ]);
        $intelligence = new CrossClientIntelligenceNotification($signal);
        $candidate = new LearningUpdate(['id' => 'learning-1']);
        $bias = new BiasMonitorSignalNotification($candidate, [
            'signal_key' => 'severity_skew',
            'module' => 'assessment',
            'dimension' => 'region',
            'dimension_label' => 'Region',
            'value' => 'North',
            'metric' => 'high_severity_rate',
            'cohort_count' => 20,
            'baseline_count' => 100,
            'cohort_high_rate' => 0.3,
            'baseline_high_rate' => 0.1,
            'rate_delta' => 0.2,
        ]);
        $fallbackBias = new BiasMonitorSignalNotification($candidate, []);

        $this->assertMailSubject($intelligence->toMail($this->recipient()), 'Cross-client industry pattern detected');
        $this->assertSame('aggregate_only', $intelligence->toArray($this->recipient())['privacy']);
        $this->assertSame('Margin pressure pattern detected for A123 across an anonymised cohort.', $intelligence->toArray($this->recipient())['message']);

        $this->assertSame('urgent', $bias->urgency());
        $this->assertMailSubject($bias->toMail($this->recipient()), 'Urgent: systematic AI bias signal detected');
        $this->assertSame('North', $bias->toArray($this->recipient())['value']);
        $this->assertStringContainsString('unknown', $fallbackBias->toArray($this->recipient())['message']);
        $this->assertSame('unknown', $fallbackBias->toArray($this->recipient())['module']);
    }

    public function test_entrepreneur_reengagement_and_governance_notifications_keep_their_recipient_context(): void
    {
        $client = $this->client('Engagement client');
        $profile = new EntrepreneurProfile(['id' => 'profile-1', 'name' => 'Entrepreneur One', 'user_id' => 'user-1']);
        $deactivation = new EntrepreneurDeactivationRequestedNotification($profile);
        $record = new OffboardingRecord(['id' => 'offboarding-1', 'client_id' => $client->getKey()]);
        $record->setRelation('client', $client);
        $reengagement = new ReengagementReminderNotification($record);
        $engagement = new NpoEngagement(['id' => 'npo-1', 'client_id' => $client->getKey()]);
        $engagement->setRelation('client', $client);
        $governance = new GovernanceReviewConversionNudgeNotification($engagement, 60);

        $this->assertMailSubject($deactivation->toMail($this->recipient()), 'Entrepreneur account deactivation requested');
        $this->assertSame('Entrepreneur One', $deactivation->toArray($this->recipient())['entrepreneur_name']);
        $this->assertMailSubject($reengagement->toMail($this->recipient()), 'Re-engagement reminder due');
        $this->assertSame('Engagement client', $reengagement->toArray($this->recipient())['client_name']);
        $this->assertMailSubject($governance->toMail($this->recipient()), 'Governance Review conversion follow-up due');
        $this->assertSame(60, $governance->toArray($this->recipient())['nudge_day']);
    }

    public function test_completed_offboarding_notifications_keep_the_client_context_without_exposing_internal_notes(): void
    {
        $client = $this->client('Offboarded client');
        $record = new OffboardingRecord([
            'id' => 'offboarding-completed-1',
            'client_id' => $client->getKey(),
            'triggered_at' => '2026-08-24 09:00:00',
        ]);
        $record->setRelation('client', $client);
        $notification = new OffboardingCompletedNotification($record);

        $this->assertSame('offboarding.completed', $notification->databaseType());
        $this->assertMailSubject($notification->toMail($this->recipient()), 'Your Future Shift Advisory offboarding documents are ready');
        $this->assertSame('Offboarded client', $notification->toArray($this->recipient())['client_name']);
        $this->assertSame('Offboarding documents ready', $notification->toArray($this->recipient())['title']);
    }

    public function test_operational_health_and_industry_briefings_include_the_actionable_context_only(): void
    {
        $run = new OperationalHealthCheckRun([
            'id' => 'run-1',
            'environment' => 'production',
            'release_version' => '2026.08.24',
        ]);
        $result = new OperationalHealthCheckResult([
            'id' => 'result-1',
            'name' => 'Queue latency',
            'check_key' => 'queue_latency',
            'status' => OperationalHealthCheckResult::STATUS_FAILED,
            'fingerprint' => 'fingerprint-1',
            'issue_summary' => 'Queue latency exceeded the threshold.',
            'consecutive_failures' => 3,
            'failures_last_7_days' => 4,
        ]);
        $health = new OperationalHealthAttentionNotification($run, $result);
        $client = $this->client('Briefing client');
        $briefing = new IndustryBriefing([
            'id' => 'briefing-1',
            'client_id' => $client->getKey(),
            'body' => 'A concise industry briefing.',
            'period' => '2026-08-01',
        ]);
        $briefing->setRelation('client', $client);
        $industry = new IndustryBriefingNotification($briefing);

        $this->assertSame('urgent', $health->urgency());
        $this->assertMailSubject($health->toMail($this->recipient()), 'Urgent: app health needs attention');
        $this->assertSame('Queue latency exceeded the threshold.', $health->toArray($this->recipient())['message']);
        $this->assertSame('2026.08.24', $health->toArray($this->recipient())['release_version']);
        $this->assertMailSubject($industry->toMail($this->recipient()), 'Monthly industry briefing');
        $this->assertSame('Briefing client', $industry->toArray($this->recipient())['client_name']);
    }

    /**
     * @return array<string, mixed>
     */
    private function referenceDataTask(string $status): array
    {
        return [
            'key' => 'industry_wacc',
            'dataset' => 'Industry WACC',
            'label' => 'Industry WACC',
            'status' => $status,
            'last_as_at' => '2026-07-01',
            'due_at' => '2026-08-31',
        ];
    }

    private function recipient(): User
    {
        return new User(['id' => 1, 'name' => 'Advisor Recipient']);
    }

    private function client(string $legalName): Client
    {
        $client = new Client(['legal_name' => $legalName]);
        $client->setAttribute('id', str($legalName)->slug()->append('-id')->toString());

        return $client;
    }

    private function assertMailSubject(MailMessage $mail, string $subject): void
    {
        $this->assertSame($subject, $mail->subject);
    }
}
