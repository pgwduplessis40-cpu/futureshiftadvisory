<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\ClientFunderRecord;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\FeeCalculation;
use App\Models\Funder;
use App\Models\LearningUpdate;
use App\Models\Meeting;
use App\Models\MessageThread;
use App\Models\Proposal;
use App\Models\Report;
use App\Models\User;
use App\Models\WellbeingCheckin;
use App\Services\Learning\LayerCadenceRegistry;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CalendarActivityTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        $this->travelTo('2026-07-15 09:00:00');
    }

    public function test_client_calendar_surfaces_timeline_activity_from_each_client_workspace_source(): void
    {
        [$user, $client] = $this->clientUserAndClient();
        Meeting::query()->create([
            'client_id' => $client->getKey(),
            'title' => 'Advisor check-in',
            'scheduled_at' => now()->addDay(),
            'location' => 'Video call',
            'link' => 'https://example.test/meeting',
            'status' => Meeting::STATUS_SCHEDULED,
            'created_by_user_id' => $user->getKey(),
        ]);
        $document = Document::query()->create([
            'client_id' => $client->getKey(),
            'category' => Document::CATEGORY_FINANCIAL_STATEMENT,
            'original_filename' => 'cashflow-forecast.pdf',
            'stored_path' => 'documents/cashflow-forecast.pdf',
            'byte_size' => 12,
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', 'cashflow forecast'),
            'uploaded_by_user_id' => $user->getKey(),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
        $report = Report::query()->create([
            'client_id' => $client->getKey(),
            'type' => ReportType::Client,
            'title' => 'Quarterly advisory report',
            'generated_by_user_id' => $user->getKey(),
            'generated_at' => now()->subDay(),
            'review_status' => 'reviewed',
            'reviewed_at' => now()->subHours(20),
            'reviewed_by_user_id' => $user->getKey(),
        ]);
        $feeCalculation = FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'method' => FeeMethod::OutcomeBased,
            'inputs' => ['fixture' => true],
            'suggested_low' => 8000,
            'suggested_mid' => 10000,
            'suggested_high' => 12000,
            'improvement_pv_total' => 25000,
            'risk_cost_pv_total' => 10000,
            'roi_ratio' => 2.5,
            'justification' => ['fixture' => true],
            'created_by_user_id' => $user->getKey(),
        ]);
        $proposal = Proposal::query()->create([
            'client_id' => $client->getKey(),
            'fee_calculation_id' => $feeCalculation->getKey(),
            'status' => ProposalStatus::Released,
            'version' => 3,
            'scope' => ['summary' => 'Advisory service fixture.'],
            'services' => [['name' => 'Advisory', 'line_total' => 10000]],
            'pv_summary' => ['fee_suggested_mid' => 10000],
            'roi_ratio' => 2.5,
            'acceptance_terms' => ['fixture' => true],
            'released_at' => now()->subDays(2),
            'released_by_user_id' => $user->getKey(),
            'expires_at' => now()->addDays(30),
            'created_by_user_id' => $user->getKey(),
        ]);
        Proposal::allowSignoffStatusTransition(function () use ($proposal, $user): void {
            $proposal->forceFill([
                'status' => ProposalStatus::AwaitingSignature,
                'awaiting_signature_at' => now()->subDay(),
            ])->save();
            $proposal->forceFill([
                'status' => ProposalStatus::Signed,
                'signed_at' => now()->subHours(12),
                'signed_by_user_id' => $user->getKey(),
            ])->save();
        });
        WellbeingCheckin::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $user->getKey(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'business_confidence' => 4,
            'personal_coping' => 4,
            'submitted_at' => now()->subHours(4),
        ]);
        $funderUpdate = LearningUpdate::query()->create([
            'layer_id' => LayerCadenceRegistry::LAYER_NPO_FUNDER_DATABASE_UPDATES,
            'source' => ['type' => 'calendar_activity_test'],
            'summary' => 'Update the funder registry for a calendar fixture.',
            'proposed_change' => ['action' => 'update_funder_registry'],
            'impact_scope' => ['surface' => 'funder_registry'],
            'clients_affected' => 0,
            'magnitude' => 'low',
            'confidence' => 0.8,
            'evidence' => ['source' => 'test'],
            'status' => LearningUpdate::STATUS_APPROVED,
        ]);
        $funder = Funder::query()->create([
            'name' => 'Calendar Foundation',
            'type' => Funder::TYPE_PHILANTHROPIC,
            'funding_windows' => [],
            'criteria' => [],
            'reporting_requirements' => [],
            'renewal_intelligence' => [],
            'last_verified_at' => now(),
            'source_learning_update_id' => $funderUpdate->getKey(),
        ]);
        ClientFunderRecord::query()->create([
            'client_id' => $client->getKey(),
            'funder_id' => $funder->getKey(),
            'grant_name' => 'Accountability grant',
            'grant_amount' => 50000,
            'currency' => 'NZD',
            'conditions' => [],
            'reporting_deadline' => now()->addMonth()->toDateString(),
        ]);
        MessageThread::query()->create([
            'client_id' => $client->getKey(),
            'created_by_user_id' => $user->getKey(),
            'subject' => 'Implementation questions',
            'last_activity_at' => now()->subMinutes(30),
        ]);

        $expectedTitles = [
            'Advisor check-in',
            'cashflow-forecast.pdf',
            'Quarterly advisory report',
            'Proposal 3 released',
            'Proposal 3 signed',
            'Proposal 3 expires',
            'Wellbeing check-in submitted',
            'Funder report: Accountability grant',
            'Message thread activity',
        ];

        $this->actingAsMfa($user)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('calendar/Index')
                ->where('title', 'Client calendar')
                ->where('canManageLeavePeriods', true)
                ->where('events', fn (mixed $events): bool => $this->calendarContainsTitles(collect($events)->all(), $expectedTitles)
                    && collect($events)->contains(fn (array $event): bool => $event['href'] === 'https://example.test/meeting')
                    && collect($events)->contains(fn (array $event): bool => $event['href'] === route('portal.documents.show', $document, absolute: false))
                    && collect($events)->contains(fn (array $event): bool => $event['href'] === route('portal.reports.show', $report, absolute: false))
                    && collect($events)->contains(fn (array $event): bool => $event['href'] === route('portal.proposals.signoff.show', $proposal, absolute: false))));
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUserAndClient(): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => 'Calendar Activity Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $user->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$user, $client];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<int, string>  $titles
     */
    private function calendarContainsTitles(array $events, array $titles): bool
    {
        $eventTitles = collect($events)->pluck('title');

        return collect($titles)->every(fn (string $title): bool => $eventTitles->contains($title));
    }
}
