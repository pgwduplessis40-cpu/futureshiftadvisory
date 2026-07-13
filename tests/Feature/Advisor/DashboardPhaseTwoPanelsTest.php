<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FeeCalculation;
use App\Models\LearningUpdate;
use App\Models\PanelMember;
use App\Models\Proposal;
use App\Models\Referral;
use App\Models\User;
use App\Services\Questionnaires\QuestionnaireOptimisationLayer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DashboardPhaseTwoPanelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_phase_two_dashboard_panels_include_scoped_proposal_status_and_questionnaire_candidates(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor('phase-two-dashboard@example.test', 'Scoped Proposal Limited');
        [, $otherClient] = $this->clientWithAdvisor('other-phase-two-dashboard@example.test', 'Other Proposal Limited');

        $proposal = $this->releasedProposal($client, now()->addDays(7));
        $this->releasedProposal($otherClient, now()->addDays(5));
        $learningUpdate = $this->questionnaireCandidate();
        $brokerReferral = $this->panelReferral($client, PanelMember::TYPE_BROKER, Referral::STAGE_BROKER_REFERRAL_SENT);
        $this->panelReferral($otherClient, PanelMember::TYPE_BROKER, Referral::STAGE_BROKER_REFERRAL_SENT);
        $coachReferral = $this->panelReferral($client, PanelMember::TYPE_COACH, Referral::STAGE_COACH_ACCEPTED);
        $brokerApplicant = $this->panelApplicant(
            PanelMember::TYPE_BROKER,
            'Pending Broker Limited',
            'pending-broker@example.test',
        );

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('proposalStatus.summary.total', 1)
                ->where('proposalStatus.summary.released', 1)
                ->where('proposalStatus.summary.expiring_soon', 1)
                ->where('proposalStatus.statuses.released', 1)
                ->where('proposalStatus.expiry_alerts.0.id', $proposal->id)
                ->where('proposalStatus.expiry_alerts.0.client_id', $client->id)
                ->where('proposalStatus.expiry_alerts.0.brief', 'Dashboard proposal fixture.')
                ->where('questionnaireOptimisation.summary.detected_candidates', 1)
                ->where('questionnaireOptimisation.items.0.questionnaire_title', 'Standard Advisory')
                ->where('panelOperations.broker.summary.total', 1)
                ->where('panelOperations.broker.summary.active', 1)
                ->where('panelOperations.broker.items.0.id', $brokerReferral->id)
                ->where('panelOperations.broker.items.0.subject_name', $client->legal_name)
                ->where('panelOperations.coach.summary.total', 1)
                ->where('panelOperations.coach.summary.active', 1)
                ->where('panelOperations.coach.items.0.id', $coachReferral->id)
                ->where('panelOperations.approvals.summary.total', 1)
                ->where('panelOperations.approvals.summary.broker', 1)
                ->where('panelOperations.approvals.summary.coach', 0)
                ->where('panelOperations.approvals.review_url', route('admin.panel-members.index', absolute: false))
                ->where('panelOperations.approvals.items.0.id', $brokerApplicant->id)
                ->where('panelOperations.approvals.items.0.panel_type', PanelMember::TYPE_BROKER)
                ->where('panelOperations.approvals.items.0.business_name', 'Pending Broker Limited')
                ->where('panelOperations.learning.summary.detected', 1)
                ->where('panelOperations.learning.queue_url', route('admin.learning-updates.index', absolute: false))
                ->where('panelOperations.learning.items.0.id', $learningUpdate->id));
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientWithAdvisor(string $email, string $clientName): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client];
    }

    private function releasedProposal(Client $client, mixed $expiresAt): Proposal
    {
        $calculation = FeeCalculation::query()->create([
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
        ]);

        return Proposal::query()->create([
            'client_id' => $client->getKey(),
            'fee_calculation_id' => $calculation->getKey(),
            'status' => ProposalStatus::Released,
            'version' => 1,
            'scope' => ['summary' => 'Dashboard proposal fixture.'],
            'services' => [['name' => 'Advisory']],
            'pv_summary' => ['target_pv' => 135000],
            'roi_ratio' => 2.5,
            'acceptance_terms' => ['phase' => 'phase_2_release_only'],
            'released_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }

    private function questionnaireCandidate(): LearningUpdate
    {
        return LearningUpdate::query()->create([
            'layer_id' => QuestionnaireOptimisationLayer::LAYER_ID,
            'source' => [
                'type' => 'questionnaire_optimisation_layer',
                'questionnaire_title' => 'Standard Advisory',
                'question_prompt' => 'Which answer was hard to complete?',
            ],
            'summary' => 'Questionnaire question has a high blank-response rate.',
            'proposed_change' => [
                'action' => 'review_questionnaire_question',
                'automatic_application' => false,
            ],
            'impact_scope' => ['questionnaire_set' => 'standard_advisory'],
            'clients_affected' => 3,
            'magnitude' => 'low',
            'confidence' => 0.72,
            'evidence' => ['blank_rate' => 0.67],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }

    private function panelReferral(Client $client, string $panelType, string $stage): Referral
    {
        $panelUser = User::factory()->withTwoFactor()->create([
            'user_type' => $panelType === PanelMember::TYPE_BROKER ? User::TYPE_BROKER : User::TYPE_COACH,
            'primary_role' => $panelType === PanelMember::TYPE_BROKER ? User::TYPE_BROKER : User::TYPE_COACH,
        ]);
        $panelUser->assignRole($panelUser->user_type);

        $panelMember = PanelMember::query()->create([
            'user_id' => $panelUser->getKey(),
            'panel_type' => $panelType,
            'status' => PanelMember::STATUS_ACTIVE,
            'application' => [
                'company' => $panelType === PanelMember::TYPE_BROKER ? 'Panel Broker Limited' : 'Panel Coach Limited',
            ],
            'approved_at' => now()->subWeek(),
        ]);

        return Referral::query()->create([
            'client_id' => $client->getKey(),
            'panel_member_id' => $panelMember->getKey(),
            'panel_type' => $panelType,
            'referral_type' => $panelType === PanelMember::TYPE_BROKER ? Referral::TYPE_BROKER : Referral::TYPE_COACH,
            'stage' => $stage,
            'payload' => ['reason' => 'Dashboard panel operations fixture.'],
            'created_by_user_id' => $client->created_by_user_id,
            'sent_at' => now()->subDay(),
        ]);
    }

    private function panelApplicant(string $panelType, string $businessName, string $email): PanelMember
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => $panelType === PanelMember::TYPE_BROKER ? User::TYPE_BROKER : User::TYPE_COACH,
            'primary_role' => $panelType === PanelMember::TYPE_BROKER ? User::TYPE_BROKER : User::TYPE_COACH,
        ]);
        $user->assignRole($user->user_type);

        return PanelMember::query()->create([
            'user_id' => $user->getKey(),
            'panel_type' => $panelType,
            'status' => PanelMember::STATUS_APPLICATION_PENDING,
            'application' => [
                'business_name' => $businessName,
                'contact_name' => 'Pending Applicant',
            ],
            'applied_at' => now(),
        ]);
    }
}
