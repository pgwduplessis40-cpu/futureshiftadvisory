<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Enums\ReportType;
use App\Jobs\ComposeReport;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ConflictDeclaration;
use App\Models\DdEngagement;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\FeeCalculation;
use App\Models\Proposal;
use App\Models\Report;
use App\Models\StrategicBudget;
use App\Models\StrategicBudgetAssessment;
use App\Models\StrategicPlan;
use App\Models\User;
use Database\Seeders\DdSpecificQuestionnaireV2Seeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DueDiligenceClientWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DdSpecificQuestionnaireV2Seeder::class);
    }

    public function test_advisor_workspace_surfaces_due_diligence_and_plan_budget_assessment_state(): void
    {
        $advisor = $this->advisor();
        [$client, $engagement, $budget] = $this->dueDiligenceClient($advisor);
        $report = $this->dueDiligenceReport($client, $engagement, 'pending_review');
        $proposal = $this->signedProposal($client, $advisor);
        $this->strategicPlan($client, $budget, $advisor);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.due_diligence.assessment_ready', false)
                ->where('client.due_diligence.assessment_status_label', 'Pending Review')
                ->where('client.due_diligence.decision_readiness.label', 'Decision gaps to resolve')
                ->where('client.due_diligence.decision_readiness.decision_label', 'Decision not ready')
                ->where('client.due_diligence.decision_readiness.client_release_ready', false)
                ->where('client.due_diligence.report_url', route('advisor.reports.download', $report, absolute: false))
                ->where('client.due_diligence.suggested_reply.id', (string) $report->getKey())
                ->where('client.due_diligence.suggested_reply.can_save', true)
                ->where('client.due_diligence.suggested_reply.can_send', true)
                ->where('client.due_diligence.suggested_reply.action_url', route('advisor.reports.dd-feedback', $report, absolute: false))
                ->where('client.due_diligence.suggested_reply.status', 'pending_review')
                ->has('client.due_diligence.suggested_reply.priorities', 3)
                ->has('client.due_diligence.report_versions', 1)
                ->where('client.due_diligence.report_versions.0.version', 1)
                ->where('client.due_diligence.report_versions.0.type_label', 'Due Diligence Report')
                ->where('client.due_diligence.report_versions.0.report_url', route('advisor.reports.download', $report, absolute: false))
                ->where('client.strategic_budget.id', (string) $budget->getKey())
                ->where('client.strategic_budget.status', StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW)
                ->where('client.strategic_budget.run_assessment_url', route('advisor.clients.strategic-budget.assess', $client, absolute: false))
                ->where('client.strategic_budget.can_run_assessment', true)
                ->where('client.strategic_budget.assessment_ready_for_approval', false)
                ->where('client.strategic_budget.assessment_action_label', 'Run assessment')
                ->where('client.strategic_budget.assessment_feedback.status', 'not_started')
                ->has('client.strategic_budget.assessment_history', 0)
                ->has('client.strategic_budget.assessment_criteria', 9)
                ->where('client.strategic_budget.assessment_criteria.0.key', 'plan_structure')
                ->where('client.strategic_budget.assessment_criteria.1.key', 'dd_evidence_linkage')
                ->where('client.strategic_budget.assessment_criteria.2.key', 'financial_evidence_quality')
                ->where('client.strategic_budget.assessment_criteria.2.status', 'review')
                ->where('client.strategic_budget.assessment_criteria.8.key', 'advisor_funder_readiness')
                ->has('client.strategic_budget.analytics.descriptive.summary')
                ->where('client.strategic_plan', null)
                ->where('client.strategic_plan_deployment_guard.allowed', false)
                ->where('client.strategic_plan_deployment_guard.missing.0', 'advisory service access')
                ->where('client.proposals.0.id', (string) $proposal->getKey())
                ->where('client.proposals.0.strategic_plan_generate_url', null));
    }

    public function test_advisor_can_run_plan_budget_assessment_without_approving_it(): void
    {
        $advisor = $this->advisor();
        [$client, , $budget] = $this->dueDiligenceClient($advisor);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.strategic-budget.assess', $client))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHas('status', 'business-plan-budget-assessed');

        $budget = $budget->refresh();

        $this->assertSame(StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW, $budget->status);
        $this->assertNotEmpty($budget->confidence);
        $assessment = StrategicBudgetAssessment::query()
            ->where('strategic_budget_id', $budget->getKey())
            ->firstOrFail();

        $this->assertSame(1, $assessment->round);
        $this->assertSame(StrategicBudgetAssessment::STATUS_ASSESSED, $assessment->status);
        $this->assertNotEmpty($assessment->snapshot);
        $this->assertCount(9, $assessment->assessment_criteria);
        $this->assertCount(3, $assessment->priorities);
        $this->assertStringContainsString('Business Plan & Budget', (string) $assessment->suggested_reply);

        $this->assertDatabaseHas('audit_events', [
            'client_id' => $client->getKey(),
            'action' => 'strategic_budget.assessed',
            'subject_id' => (string) $budget->getKey(),
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('client.strategic_budget.assessment_ready_for_approval', true)
                ->where('client.strategic_budget.assessment_feedback.status', StrategicBudgetAssessment::STATUS_ASSESSED)
                ->where('client.strategic_budget.assessment_feedback.version', 1)
                ->where('client.strategic_budget.assessment_feedback.can_send', true)
                ->has('client.strategic_budget.assessment_feedback.priorities', 3)
                ->has('client.strategic_budget.assessment_history', 1)
                ->where('client.strategic_budget.assessment_history.0.version', 1)
                ->where('client.strategic_budget.assessment_history.0.status', StrategicBudgetAssessment::STATUS_ASSESSED));
    }

    public function test_advisor_can_save_and_send_plan_budget_assessment_feedback_to_the_client(): void
    {
        Notification::fake();

        $advisor = $this->advisor();
        [$client, , $budget] = $this->dueDiligenceClient($advisor);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.strategic-budget.assess', $client))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.clients.strategic-budget.feedback', $client), [
                'advisor_feedback' => 'Private advisor summary: funding evidence and customer concentration need one more check before approval.',
                'proposed_reply' => 'Please strengthen the funding evidence and customer concentration explanation, then resubmit the Business Plan & Budget.',
                'send_to_client' => false,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHas('status', 'business-plan-budget-feedback-saved');

        $assessment = StrategicBudgetAssessment::query()
            ->where('strategic_budget_id', $budget->getKey())
            ->firstOrFail();

        $this->assertSame(StrategicBudgetAssessment::STATUS_FEEDBACK_SAVED, $assessment->status);
        $this->assertNull($assessment->client_message_thread_id);
        $this->assertTrue(data_get($assessment->feedback_snapshot, 'advisor_edits.feedback_changed_from_suggestion'));

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.clients.strategic-budget.feedback', $client), [
                'advisor_feedback' => 'Private advisor summary: the revised draft is ready to send back to the client for BP&B updates.',
                'proposed_reply' => 'Please update the BP&B evidence points we discussed and send it back for the next assessment version.',
                'send_to_client' => true,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHas('status', 'business-plan-budget-feedback-sent');

        $assessment = $assessment->refresh();

        $this->assertSame(StrategicBudgetAssessment::STATUS_FEEDBACK_SENT, $assessment->status);
        $this->assertNotNull($assessment->client_message_thread_id);
        $this->assertNotNull($assessment->client_message_id);
        $this->assertDatabaseHas('message_threads', [
            'id' => $assessment->client_message_thread_id,
            'client_id' => $client->getKey(),
            'subject' => 'Business Plan & Budget assessment feedback',
        ]);
        $this->assertDatabaseHas('messages', [
            'id' => $assessment->client_message_id,
            'body' => 'Please update the BP&B evidence points we discussed and send it back for the next assessment version.',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'client_id' => $client->getKey(),
            'action' => 'strategic_budget.assessment_feedback_sent',
            'subject_id' => (string) $assessment->getKey(),
        ]);
    }

    public function test_due_diligence_action_buttons_queue_distinct_report_jobs_with_feedback(): void
    {
        Queue::fake();

        $advisor = $this->advisor();
        [$client] = $this->dueDiligenceClient($advisor);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.reports.store', $client), [
                'type' => ReportType::DueDiligence->value,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHas('status', 'dd-assessment-generation-queued')
            ->assertSessionHas('toast.message', 'DD assessment has been queued for background generation.');

        Queue::assertPushed(
            ComposeReport::class,
            fn (ComposeReport $job): bool => $job->clientId === (string) $client->getKey()
                && $job->reportType === ReportType::DueDiligence->value,
        );

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.reports.store', $client), [
                'type' => ReportType::AcquisitionGoNoGo->value,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHas('status', 'dd-decision-report-generation-queued')
            ->assertSessionHas('toast.message', 'DD decision report has been queued for background generation.');

        Queue::assertPushed(
            ComposeReport::class,
            fn (ComposeReport $job): bool => $job->clientId === (string) $client->getKey()
                && $job->reportType === ReportType::AcquisitionGoNoGo->value,
        );
    }

    public function test_advisor_can_save_and_send_due_diligence_suggested_reply_to_the_client(): void
    {
        Notification::fake();

        $advisor = $this->advisor();
        [$client, $engagement] = $this->dueDiligenceClient($advisor);
        $report = $this->dueDiligenceReport($client, $engagement, 'reviewed');

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.reports.dd-feedback', $report), [
                'advisor_feedback' => 'Private DD summary: price protection remains the key point before buyer reliance.',
                'proposed_reply' => 'Please review the DD decision gaps around price protection before confirming whether to buy.',
                'send_to_client' => false,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHas('status', 'dd-feedback-saved');

        $report = $report->refresh();

        $this->assertSame('feedback_saved', data_get($report->metadata, 'advisor_client_reply.status'));
        $this->assertSame(
            'Private DD summary: price protection remains the key point before buyer reliance.',
            data_get($report->metadata, 'advisor_client_reply.advisor_feedback'),
        );
        $this->assertNull(data_get($report->metadata, 'advisor_client_reply.client_message_thread_id'));
        $this->assertDatabaseHas('audit_events', [
            'client_id' => $client->getKey(),
            'action' => 'dd.report_feedback_saved',
            'subject_id' => (string) $report->getKey(),
        ]);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.reports.dd-feedback', $report), [
                'advisor_feedback' => 'Private DD summary: send the buyer-facing DD decision explanation.',
                'proposed_reply' => 'Based on the information available at assessment time, please use this DD report to decide whether to buy, renegotiate, pause, or walk away.',
                'send_to_client' => true,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHas('status', 'dd-feedback-sent');

        $report = $report->refresh();
        $threadId = data_get($report->metadata, 'advisor_client_reply.client_message_thread_id');
        $messageId = data_get($report->metadata, 'advisor_client_reply.client_message_id');

        $this->assertSame('feedback_sent', data_get($report->metadata, 'advisor_client_reply.status'));
        $this->assertNotNull($threadId);
        $this->assertNotNull($messageId);
        $this->assertDatabaseHas('message_threads', [
            'id' => $threadId,
            'client_id' => $client->getKey(),
            'subject' => 'Due Diligence assessment feedback',
        ]);
        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'body' => 'Based on the information available at assessment time, please use this DD report to decide whether to buy, renegotiate, pause, or walk away.',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'client_id' => $client->getKey(),
            'action' => 'dd.report_feedback_sent',
            'subject_id' => (string) $report->getKey(),
        ]);
    }

    public function test_due_diligence_strategic_plan_cannot_deploy_from_dd_workspace_even_after_assessment(): void
    {
        $advisor = $this->advisor();
        [$client, $engagement, $budget] = $this->dueDiligenceClient($advisor);
        $report = $this->dueDiligenceReport($client, $engagement, 'pending_review');
        $plan = $this->strategicPlan($client, $budget, $advisor);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.strategic-plans.deploy', $plan))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHasErrors('strategic_plan');

        $this->assertDatabaseHas('strategic_plans', [
            'id' => $plan->getKey(),
            'status' => StrategicPlan::STATUS_DRAFT,
        ]);

        $report->forceFill([
            'review_status' => 'reviewed',
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $advisor->getKey(),
        ])->save();
        $budget->forceFill([
            'status' => StrategicBudget::STATUS_ADVISOR_APPROVED,
            'approved_at' => now(),
            'approved_by_user_id' => $advisor->getKey(),
            'business_plan_approved_at' => now(),
            'business_plan_approved_by_user_id' => $advisor->getKey(),
        ])->save();

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.strategic-plans.deploy', $plan))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHasErrors('strategic_plan');

        $this->assertDatabaseHas('strategic_plans', [
            'id' => $plan->getKey(),
            'status' => StrategicPlan::STATUS_DRAFT,
        ]);
    }

    /**
     * @return array{0: Client, 1: DdEngagement, 2: StrategicBudget}
     */
    private function dueDiligenceClient(User $advisor): array
    {
        $buyer = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $buyer->assignRole(User::TYPE_CLIENT_PRIMARY);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::DUE_DILIGENCE->value,
            'legal_name' => 'Southern Lights Holdings Limited',
            'trading_name' => 'Southern Lights',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $buyer->getKey(),
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => ['portal', 'due_diligence'],
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $buyer->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => ['portal', 'due_diligence'],
        ]);

        $conflict = ConflictDeclaration::query()->create([
            'client_id' => $client->getKey(),
            'advisor_id' => $advisor->getKey(),
            'declaration' => ['referral_type' => 'due_diligence'],
            'declared_at' => now(),
        ]);

        $engagement = DdEngagement::query()->create([
            'client_id' => $client->getKey(),
            'target_name' => 'Kauri Kitchens Group Limited',
            'target_details' => [
                'industry' => 'Manufacturing',
                'nzbn' => '942900000034',
                'vendor_name' => 'Kauri Kitchens Vendor Limited',
                'client_capability' => [
                    'mode' => 'guided',
                    'support_level' => 'guided',
                    'dd_experience' => 'first_time',
                    'business_ownership_experience' => 'none',
                    'financial_confidence' => 'low',
                    'preferred_guidance' => 'balanced',
                    'captured_from' => 'test',
                    'captured_at' => now()->toIso8601String(),
                ],
            ],
            'status' => DdEngagement::STATUS_IN_PROGRESS,
            'conflict_declaration_id' => $conflict->getKey(),
            'created_by_user_id' => $advisor->getKey(),
            'disclaimer_acknowledged_at' => now(),
        ]);

        $document = Document::query()->create([
            'client_id' => $client->getKey(),
            'category' => Document::CATEGORY_FINANCIAL_STATEMENT,
            'original_filename' => 'target-management-accounts.xlsx',
            'stored_path' => 'testing/target-management-accounts.xlsx',
            'byte_size' => 1024,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'sha256' => str_repeat('a', 64),
            'uploaded_by_user_id' => $buyer->getKey(),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
        DocumentVerification::query()->create([
            'client_id' => $client->getKey(),
            'document_id' => $document->getKey(),
            'context_hash' => hash('sha256', 'dd-workspace-financials-'.$document->getKey()),
            'claim_text' => 'The uploaded management accounts are verified for DD budget reliance.',
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
            'confidence' => 0.95,
            'verified_at' => now(),
            'resolved_by_user_id' => $advisor->getKey(),
        ]);

        $budget = StrategicBudget::query()->create([
            'client_id' => $client->getKey(),
            'pathway' => StrategicBudget::PATHWAY_DUE_DILIGENCE,
            'label' => 'Business Plan & Budget',
            'status' => StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW,
            'horizon_months' => 24,
            'source_financials' => [],
            'client_goals' => [],
            'advisor_goals' => [],
            'business_plan_sections' => $this->completedBusinessPlanSections(),
            'business_plan_source_drafts' => [],
            'business_plan_prompts' => [],
            'assumptions' => [],
            'implementation_costs' => [[
                'label' => 'Professional advice',
                'amount' => 12000,
                'quantity' => 1,
                'confidence' => 'known',
            ]],
            'monthly_fixed_costs' => [[
                'label' => 'Operating support',
                'amount' => 4200,
                'quantity' => 1,
                'confidence' => 'estimate',
            ]],
            'future_costs' => [],
            'revenue_forecast' => [],
            'funding_sources' => [[
                'label' => 'Funding available',
                'amount' => 800000,
                'quantity' => 1,
                'confidence' => 'known',
            ]],
            'funding_scenarios' => [],
            'computed' => [],
            'flags' => [],
            'confidence' => [],
            'submitted_at' => now(),
            'business_plan_submitted_at' => now(),
        ]);

        return [$client, $engagement, $budget];
    }

    private function dueDiligenceReport(Client $client, DdEngagement $engagement, string $reviewStatus): Report
    {
        return Report::query()->create([
            'client_id' => $client->getKey(),
            'type' => ReportType::DueDiligence,
            'title' => 'Kauri Kitchens Due Diligence Report',
            'generated_at' => now(),
            'metadata' => ['dd_engagement_id' => $engagement->getKey()],
            'review_status' => $reviewStatus,
        ]);
    }

    private function strategicPlan(Client $client, StrategicBudget $budget, User $advisor): StrategicPlan
    {
        return StrategicPlan::query()->create([
            'client_id' => $client->getKey(),
            'strategic_budget_id' => $budget->getKey(),
            'title' => 'Strategic Plan - Southern Lights',
            'status' => StrategicPlan::STATUS_DRAFT,
            'duration_months' => 12,
            'complexity_band' => 'standard',
            'duration_rationale' => [],
            'sections' => [],
            'generated_at' => now(),
            'generated_by_user_id' => $advisor->getKey(),
        ]);
    }

    private function signedProposal(Client $client, User $advisor): Proposal
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
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $proposal = Proposal::query()->create([
            'client_id' => $client->getKey(),
            'fee_calculation_id' => $calculation->getKey(),
            'status' => ProposalStatus::Released,
            'version' => 1,
            'scope' => ['summary' => 'DD advisory service fixture.'],
            'services' => [['name' => 'Due diligence', 'line_total' => 10000]],
            'pv_summary' => ['fee_suggested_mid' => 10000],
            'roi_ratio' => 2.5,
            'acceptance_terms' => ['fixture' => true],
            'released_at' => now(),
            'released_by_user_id' => $advisor->getKey(),
            'expires_at' => now()->addDays(30),
            'created_by_user_id' => $advisor->getKey(),
        ]);

        return Proposal::allowSignoffStatusTransition(function () use ($proposal, $advisor): Proposal {
            $proposal->forceFill([
                'status' => ProposalStatus::AwaitingSignature,
                'awaiting_signature_at' => now(),
            ])->save();

            $proposal->forceFill([
                'status' => ProposalStatus::Signed,
                'signed_at' => now(),
                'signed_by_user_id' => $advisor->getKey(),
            ])->save();

            return $proposal->refresh();
        });
    }

    /**
     * @return array<int, array{key: string, title: string, prompt: string, answer: string}>
     */
    private function completedBusinessPlanSections(): array
    {
        return collect([
            'goals' => 'Goals',
            'current_position' => 'Current position',
            'market_customers' => 'Market / customers',
            'operations' => 'Operations',
            'risks' => 'Risks',
            'swot' => 'SWOT',
            'action_priorities' => 'Action priorities',
            'evidence_documents' => 'Evidence documents',
        ])
            ->map(fn (string $title, string $key): array => [
                'key' => $key,
                'title' => $title,
                'prompt' => 'Complete '.$title,
                'answer' => 'Advisor-ready DD-informed answer for '.$title.'.',
            ])
            ->values()
            ->all();
    }

    private function advisor(): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }
}
