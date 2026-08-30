<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Enums\Permission;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\IntegrationHealthSample;
use App\Models\PlanAssessment;
use App\Models\PlanRevision;
use App\Models\ProspectLead;
use App\Models\RatingFramework;
use App\Models\RedFlag;
use App\Models\ServiceActivation;
use App\Models\ServiceRateSetting;
use App\Models\TermsAcceptance;
use App\Models\TermsEnforcement;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Ai\AdvisorAiNotice;
use App\Support\RequestContext;
use Database\Seeders\RatingFrameworkSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_advisor_dashboard_scopes_live_widgets_to_assigned_clients(): void
    {
        $advisor = $this->advisor('advisor@example.test');
        $advisor->givePermissionTo(Permission::INTEGRATION_HEALTH_VIEW->value);
        $otherAdvisor = $this->advisor('other-advisor@example.test');
        $client = $this->clientFor($advisor, 'Scoped Health Limited', Client::DATA_QUALITY_LOW);
        $otherClient = $this->clientFor($otherAdvisor, 'Other Advisor Limited', Client::DATA_QUALITY_INSUFFICIENT);
        $contact = $this->clientContactFor($client, 'Client Contact', 'contact@example.test');
        $otherContact = $this->clientContactFor($otherClient, 'Other Contact', 'other-contact@example.test');

        [$priorTerms, $latestTerms] = $this->publishedTerms();
        TermsEnforcement::query()->create([
            'scope' => TermsEnforcement::SCOPE_PLATFORM,
            'activated_by_user_id' => $advisor->getKey(),
            'activated_at' => now(),
        ]);

        $this->acceptTerms($advisor, $latestTerms);
        $this->acceptTerms($contact, $priorTerms, now()->subDay());
        $this->acceptTerms($otherContact, $priorTerms, now()->subDay());

        $documentFlag = $this->documentFlagFor($client, 'scoped-support.pdf');
        $this->documentFlagFor($otherClient, 'other-support.pdf');
        $this->redFlagFor($client, 'Scoped critical flag');
        $this->redFlagFor($otherClient, 'Other critical flag');
        $this->prospectLead('Lead One', 'lead-one@example.test');
        $this->prospectLead('Lead Two', 'lead-two@example.test');
        $this->integrationSample('nzbn', IntegrationHealthSample::HEALTH_GREEN);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('clientsHealth.summary.total', 1)
                ->where('clientsHealth.summary.needs_attention', 1)
                ->where('clientsHealth.clients.0.legal_name', 'Scoped Health Limited')
                ->where(
                    'clientsHealth.clients.0.show_url',
                    route('advisor.clients.show', $client, absolute: false),
                )
                ->where('clientsHealth.clients.0.engagement.level', 'red')
                ->where('clientsHealth.clients.0.engagement.score', 25)
                ->where('clientsHealth.clients.0.engagement.scores.questionnaire_pct', 0)
                ->where('clientsHealth.clients.0.engagement.scores.documents_pct', 0)
                ->where('clientsHealth.clients.0.engagement.scores.milestones_on_track_pct', 100)
                ->where('clientsHealth.clients.0.engagement.scores.comms_recency_pct', 0)
                ->where('clientsHealth.clients.0.engagement.display.overdue_count', 0)
                ->where('clientsHealth.clients.0.engagement.display.blocked_count', 0)
                ->where('clientsHealth.clients.0.engagement.display.last_comms_days', null)
                ->where('clientsHealth.clients.0.engagement.weakest_component', 'questionnaire_pct')
                ->where('clientsHealth.clients.0.engagement.focus_section', 'questionnaire')
                ->where(
                    'clientsHealth.clients.0.engagement.drill_url',
                    route('advisor.clients.show', ['client' => $client, 'focus' => 'questionnaire'], absolute: false),
                )
                ->where('clientsHealth.clients.0.open_document_flags_count', 1)
                ->has('clientsHealth.clients', 1)
                ->where('redFlags.summary.open', 1)
                ->where('redFlags.summary.unacknowledged', 1)
                ->where('redFlags.items.0.headline', 'Scoped critical flag')
                ->where('redFlags.items.0.client_name', 'Scoped Health Limited')
                ->has('documentVerificationFlags', 1)
                ->where('documentVerificationFlags.0.id', $documentFlag->id)
                ->where('documentVerificationFlags.0.client_name', 'Scoped Health Limited')
                ->where('documentVerificationFlags.0.document_name', 'scoped-support.pdf')
                ->where('pendingTermsReacceptance.total', 1)
                ->where('pendingTermsReacceptance.items.0.user_name', 'Client Contact')
                ->where('prospectInbox.total', 2)
                ->where('prospectInbox.triage_enabled', true)
                ->where('feeStatus.free_access_mode', false)
                ->where('feeStatus.charging_enabled', true)
                ->where('feeStatus.can_manage', false)
                ->where('feeStatus.manage_url', null)
                ->loadDeferredProps('advisor-signals', fn (Assert $page): Assert => $page
                    ->where('integrationHealth.summary.total', 1)
                    ->where('integrationHealth.services.0.service', 'nzbn')));
    }

    public function test_super_admin_dashboard_surfaces_inactive_rates_free_access_mode(): void
    {
        $admin = User::factory()->superAdmin()->withTwoFactor()->create([
            'email' => 'dashboard-fees-admin@example.test',
        ]);
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        ServiceRateSetting::query()->create([
            'hourly_rate' => 325,
            'currency' => 'NZD',
            'npo_service_discount_percent' => 30,
            'npo_retainer_discount_percent' => 35,
            'effective_from' => now()->subMinute(),
            'is_active' => false,
            'free_access_enabled' => true,
            'free_access_enabled_at' => now(),
        ]);

        $this->actingAsMfa($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('feeStatus.free_access_mode', true)
                ->where('feeStatus.charging_enabled', false)
                ->where('feeStatus.can_manage', true)
                ->where('feeStatus.manage_url', route('admin.service-rates.index', absolute: false)));
    }

    public function test_advisor_can_resolve_dashboard_document_verification_flag(): void
    {
        $advisor = $this->advisor('verification-advisor@example.test');
        $client = $this->clientFor($advisor, 'Verification Client Limited', Client::DATA_QUALITY_LOW);
        $verification = $this->documentFlagFor($client, 'verification-support.pdf');
        $note = 'Reviewed the uploaded file against the stated claim and requested corrected evidence before use.';

        $this->from(route('dashboard', absolute: false))
            ->actingAsMfa($advisor)
            ->patch(route('advisor.document-verifications.update', $verification), [
                'resolution_note' => $note,
            ])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('status', 'document-verification-resolved');

        $verification->refresh();

        $this->assertNotNull($verification->resolved_at);
        $this->assertSame($advisor->getKey(), $verification->resolved_by_user_id);
        $this->assertSame($note, $verification->resolution_note);
        $this->assertFalse($verification->isBlockingAnalysis());
    }

    public function test_advisor_dashboard_surfaces_entrepreneur_idea_and_plan_reviews(): void
    {
        $advisor = $this->advisor('entrepreneur-reviews@example.test');
        $otherAdvisor = $this->advisor('other-entrepreneur-reviews@example.test');
        $profile = $this->entrepreneurProfileFor($advisor, 'Wessel Du Plessis', 'wessel@example.test');
        $otherProfile = $this->entrepreneurProfileFor($otherAdvisor, 'Other Founder', 'other-founder@example.test');

        $validation = IdeaValidation::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'evaluated_by_user_id' => $profile->user_id,
            'problem' => 'A clear customer problem.',
            'target_customer' => 'Early-stage service founders.',
            'solution' => 'A guided planning workspace.',
            'value_proposition' => 'Less overwhelm and clearer advisor review.',
            'demand_signal' => 'Founder requested guided help.',
            'revenue_model' => 'Subscription and advisory conversion.',
            'ai_evaluation' => ['summary' => 'Ready for advisor review.'],
            'viability_alerts' => [],
            'evaluated_at' => now()->subHour(),
        ]);
        IdeaValidation::query()->create([
            'entrepreneur_profile_id' => $otherProfile->getKey(),
            'evaluated_by_user_id' => $otherProfile->user_id,
            'problem' => 'Other problem.',
            'target_customer' => 'Other customers.',
            'solution' => 'Other solution.',
            'value_proposition' => 'Other proposition.',
            'demand_signal' => 'Other demand signal.',
            'revenue_model' => 'Other revenue model.',
            'ai_evaluation' => ['summary' => 'Other review.'],
            'viability_alerts' => [],
            'evaluated_at' => now()->subMinutes(30),
        ]);

        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'title' => 'Wessel launch plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_ASSESSING,
            'current_phase' => 1,
            'created_by_user_id' => $profile->user_id,
        ]);
        $this->seed(RatingFrameworkSeeder::class);
        $assessment = PlanAssessment::query()->create([
            'business_plan_id' => $plan->getKey(),
            'rating_framework_id' => RatingFramework::query()->firstOrFail()->getKey(),
            'round' => 1,
            'ai_scores' => [],
            'overall_grade' => 'needs_work',
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('entrepreneurReviews.summary.total', 2)
                ->where('entrepreneurReviews.summary.idea_validations', 1)
                ->where('entrepreneurReviews.summary.business_plans', 1)
                ->where('clientsHealth.summary.advisory_clients', 0)
                ->where('clientsHealth.summary.entrepreneurs', 1)
                ->where('clientsHealth.summary.total', 1)
                ->where('entrepreneurReviews.items.0.id', $validation->id)
                ->where('entrepreneurReviews.items.0.type', 'idea_validation')
                ->where('entrepreneurReviews.items.0.label', 'Idea validation')
                ->where('entrepreneurReviews.items.0.entrepreneur_name', 'Wessel Du Plessis')
                ->where('entrepreneurReviews.items.0.action_label', 'Review idea')
                ->where('entrepreneurReviews.items.1.id', $plan->id)
                ->where('entrepreneurReviews.items.1.type', 'business_plan')
                ->where('entrepreneurReviews.items.1.label', 'Business plan')
                ->where('entrepreneurReviews.items.1.entrepreneur_name', 'Wessel Du Plessis')
                ->where('entrepreneurReviews.items.1.status', 'Assessment ready for feedback')
                ->where('entrepreneurReviews.items.1.action_label', 'Review assessment')
                ->where('entrepreneurReviews.items.1.detail_url', route('advisor.entrepreneurs.assessments.show', [$profile, $assessment], absolute: false))
                ->has('entrepreneurReviews.items', 2));
    }

    public function test_advisor_dashboard_marks_sent_assessment_feedback_as_awaiting_resubmission(): void
    {
        $advisor = $this->advisor('assessment-feedback-sent-dashboard@example.test');
        $profile = $this->entrepreneurProfileFor($advisor, 'Tania Hassounia', 'tania@example.test');

        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'title' => 'Tania business plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_ASSESSING,
            'current_phase' => 5,
            'created_by_user_id' => $profile->user_id,
        ]);
        $this->seed(RatingFrameworkSeeder::class);
        PlanAssessment::query()->create([
            'business_plan_id' => $plan->getKey(),
            'rating_framework_id' => RatingFramework::query()->firstOrFail()->getKey(),
            'round' => 1,
            'ai_scores' => [],
            'mentor_notes' => [
                'advisor_feedback' => 'Strengthen the opening evidence before the next assessment.',
                'feedback_sent_at' => now()->subHour()->toIso8601String(),
            ],
            'overall_grade' => 'needs_work',
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('entrepreneurReviews.summary.total', 0)
                ->where('entrepreneurReviews.summary.idea_validations', 0)
                ->where('entrepreneurReviews.summary.business_plans', 0)
                ->where('entrepreneurReviews.items.0.type', 'business_plan')
                ->where('entrepreneurReviews.items.0.entrepreneur_name', 'Tania Hassounia')
                ->where('entrepreneurReviews.items.0.status', 'Changes requested')
                ->where('entrepreneurReviews.items.0.action_label', 'Await resubmission')
                ->where('entrepreneurReviews.items.0.detail_url', route('advisor.entrepreneurs.show', $profile, absolute: false))
                ->has('entrepreneurReviews.items', 1));
    }

    public function test_advisor_dashboard_marks_submitted_plan_with_prior_feedback_as_ready_to_reassess(): void
    {
        $advisor = $this->advisor('assessment-resubmitted-dashboard@example.test');
        $profile = $this->entrepreneurProfileFor($advisor, 'Tania Hassounia', 'tania@example.test');

        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'title' => 'Tania business plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_BUILDING,
            'current_phase' => 5,
            'created_by_user_id' => $profile->user_id,
        ]);
        $plan->forceFill([
            'status' => BusinessPlan::STATUS_SUBMITTED,
            'submitted_at' => now()->subWeeks(2),
        ])->save();
        $plan->forceFill(['updated_at' => now()->subMinute()])->save();
        $this->seed(RatingFrameworkSeeder::class);
        PlanAssessment::query()->create([
            'business_plan_id' => $plan->getKey(),
            'rating_framework_id' => RatingFramework::query()->firstOrFail()->getKey(),
            'round' => 1,
            'ai_scores' => [],
            'mentor_notes' => [
                'advisor_feedback' => 'Strengthen the opening evidence before the next assessment.',
                'feedback_sent_at' => now()->subDay()->toIso8601String(),
            ],
            'overall_grade' => 'needs_work',
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('entrepreneurReviews.summary.total', 1)
                ->where('entrepreneurReviews.summary.idea_validations', 0)
                ->where('entrepreneurReviews.summary.business_plans', 1)
                ->where('entrepreneurReviews.items.0.type', 'business_plan')
                ->where('entrepreneurReviews.items.0.entrepreneur_name', 'Tania Hassounia')
                ->where('entrepreneurReviews.items.0.status', 'Submitted for assessment')
                ->where('entrepreneurReviews.items.0.action_label', 'Run reassessment')
                ->where('entrepreneurReviews.items.0.detail_url', route('advisor.entrepreneurs.show', $profile, absolute: false))
                ->has('entrepreneurReviews.items', 1));
    }

    public function test_advisor_dashboard_uses_latest_resubmitted_assessment_round(): void
    {
        $advisor = $this->advisor('assessment-latest-round-dashboard@example.test');
        $profile = $this->entrepreneurProfileFor($advisor, 'Tania Hassounia', 'tania@example.test');

        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'title' => 'Tania business plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_BUILDING,
            'current_phase' => 5,
            'created_by_user_id' => $profile->user_id,
        ]);
        $plan->forceFill([
            'status' => BusinessPlan::STATUS_SUBMITTED,
            'submitted_at' => now()->subWeeks(2),
        ])->save();
        $plan->forceFill(['status' => BusinessPlan::STATUS_ASSESSING])->save();
        $this->seed(RatingFrameworkSeeder::class);
        $framework = RatingFramework::query()->firstOrFail();
        PlanAssessment::query()->create([
            'business_plan_id' => $plan->getKey(),
            'rating_framework_id' => $framework->getKey(),
            'round' => 1,
            'ai_scores' => [],
            'mentor_notes' => [
                'advisor_feedback' => 'Strengthen the opening evidence before the next assessment.',
                'feedback_sent_at' => now()->subDay()->toIso8601String(),
            ],
            'overall_grade' => 'needs_work',
        ]);
        $latestAssessment = PlanAssessment::query()->create([
            'business_plan_id' => $plan->getKey(),
            'rating_framework_id' => $framework->getKey(),
            'round' => 2,
            'ai_scores' => [],
            'mentor_notes' => [],
            'overall_grade' => 'needs_work',
        ]);
        PlanRevision::query()->create([
            'business_plan_id' => $plan->getKey(),
            'round' => 2,
            'submitted_at' => now()->subHour(),
            'progress_comparison' => [
                'trajectory_percent' => 10,
                'overall_delta' => 5,
            ],
            'submitted_by_user_id' => $profile->user_id,
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('entrepreneurReviews.summary.total', 1)
                ->where('entrepreneurReviews.summary.idea_validations', 0)
                ->where('entrepreneurReviews.summary.business_plans', 1)
                ->where('entrepreneurReviews.items.0.type', 'business_plan')
                ->where('entrepreneurReviews.items.0.entrepreneur_name', 'Tania Hassounia')
                ->where('entrepreneurReviews.items.0.status', 'Assessment ready for feedback')
                ->where('entrepreneurReviews.items.0.action_label', 'Review assessment')
                ->where('entrepreneurReviews.items.0.detail_url', route('advisor.entrepreneurs.assessments.show', [$profile, $latestAssessment], absolute: false))
                ->has('entrepreneurReviews.items', 1));
    }

    public function test_advisor_dashboard_excludes_change_requested_idea_validations_from_review_counts(): void
    {
        $advisor = $this->advisor('idea-feedback-sent-dashboard@example.test');
        $profile = $this->entrepreneurProfileFor($advisor, 'Andrew Sing', 'andrew@example.test');

        IdeaValidation::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'evaluated_by_user_id' => $profile->user_id,
            'problem' => 'A clear customer problem.',
            'target_customer' => 'Early-stage service founders.',
            'solution' => 'A guided planning workspace.',
            'value_proposition' => 'Less overwhelm and clearer advisor review.',
            'demand_signal' => 'Founder requested guided help.',
            'revenue_model' => 'Subscription and advisory conversion.',
            'ai_evaluation' => [
                'summary' => 'Changes requested.',
                'metadata' => [
                    'advisor_gate_status' => 'changes_requested',
                ],
            ],
            'viability_alerts' => [],
            'evaluated_at' => now()->subHour(),
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('entrepreneurReviews.summary.total', 0)
                ->where('entrepreneurReviews.summary.idea_validations', 0)
                ->where('entrepreneurReviews.summary.business_plans', 0)
                ->where('entrepreneurReviews.items.0.type', 'idea_validation')
                ->where('entrepreneurReviews.items.0.entrepreneur_name', 'Andrew Sing')
                ->where('entrepreneurReviews.items.0.status', 'Changes requested')
                ->where('entrepreneurReviews.items.0.action_label', 'Await resubmission')
                ->has('entrepreneurReviews.items', 1));
    }

    public function test_client_primary_user_still_redirects_to_portal_dashboard(): void
    {
        $clientUser = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);
        $client = $this->clientFor($this->advisor('portal-advisor@example.test'), 'Portal Client Limited');

        app(RequestContext::class)->apply('system', [], (string) $clientUser->getKey());
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $clientUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        $this->actingAsMfa($clientUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('portal.dashboard', absolute: false));
    }

    public function test_active_entrepreneur_client_uses_one_canonical_advisor_workspace(): void
    {
        $advisor = $this->advisor('canonical-workspace@example.test');
        $client = $this->clientFor($advisor, 'Canonical Workspace Limited');
        $profile = $this->entrepreneurProfileFor(
            $advisor,
            'Canonical Founder',
            'canonical-founder@example.test',
        );
        $profile->forceFill(['client_id' => $client->getKey()])->save();
        BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'title' => 'Canonical founder plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_BUILDING,
            'current_phase' => 5,
            'created_by_user_id' => $profile->user_id,
        ]);
        $duplicate = $this->entrepreneurProfileFor(
            $advisor,
            'Empty duplicate workspace',
            'canonical-empty-duplicate@example.test',
        );

        ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $duplicate->user_id,
            'advisor_id' => $advisor->getKey(),
            'service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'client_label' => 'Test new Business Idea',
            'status' => ServiceActivation::STATUS_ACTIVE,
            'related_entrepreneur_profile_id' => $duplicate->getKey(),
            'accepted_at' => now(),
        ]);

        $showUrl = route('advisor.entrepreneurs.show', $profile, absolute: false);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('clientsHealth.clients.0.show_url', $showUrl)
                ->where('clientsHealth.clients.0.engagement.drill_url', $showUrl));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertRedirect($showUrl);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', [
                'client' => $client,
                'focus' => 'questionnaire',
                'highlight' => 'missing-financials',
                'client_workspace' => 1,
            ]))
            ->assertRedirect($showUrl);

        $this->actingAsMfa($advisor)
            ->get($showUrl)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/entrepreneurs/Show')
                ->where(
                    'entrepreneur.client_actions.email_url',
                    route('advisor.clients.compose', $client, absolute: false),
                )
                ->where(
                    'entrepreneur.client_actions.offboard_url',
                    route('advisor.clients.offboarding.create', $client, absolute: false),
                ));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.show', $duplicate))
            ->assertRedirect($showUrl);
    }

    public function test_client_with_an_entrepreneur_primary_contact_uses_the_entrepreneur_workspace(): void
    {
        $advisor = $this->advisor('contact-workspace@example.test');
        $profile = $this->entrepreneurProfileFor(
            $advisor,
            'Primary Contact Founder',
            'primary-contact-founder@example.test',
        );
        $client = $this->clientFor($advisor, 'Legacy Primary Contact Limited');
        $client->forceFill(['primary_contact_user_id' => $profile->user_id])->save();
        $showUrl = route('advisor.entrepreneurs.show', $profile, absolute: false);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('clientsHealth.clients.0.show_url', $showUrl)
                ->where('clientsHealth.clients.0.engagement.drill_url', $showUrl));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertRedirect($showUrl);
    }

    public function test_advisor_dashboard_surfaces_ai_provider_failure_as_an_action(): void
    {
        $advisor = $this->advisor('ai-provider-action@example.test');
        $advisor->givePermissionTo(Permission::INTEGRATION_HEALTH_VIEW->value);
        Cache::put(AdvisorAiNotice::CACHE_KEY, [
            'message' => 'AI analysis deferred.',
            'reason' => 'Anthropic API request failed with status 400.',
            'prompt_id' => 'entrepreneur.idea_validation',
            'recorded_at' => now()->toIso8601String(),
        ], now()->addMinute());

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('aiOperationalAlert.available', true)
                ->where('aiOperationalAlert.total', 1)
                ->where('aiOperationalAlert.reason', 'Anthropic API request failed with status 400.')
                ->where('aiOperationalAlert.action_url', route('admin.integration-health.index', absolute: false)));
    }

    public function test_advisor_dashboard_does_not_surface_an_alert_from_the_superseded_provider_cache_key(): void
    {
        $advisor = $this->advisor('legacy-ai-provider-action@example.test');
        $advisor->givePermissionTo(Permission::INTEGRATION_HEALTH_VIEW->value);
        Cache::put('fsa.ai.unavailable.latest_notice', [
            'reason' => 'Historical AI provider failure.',
            'recorded_at' => now()->subDay()->toIso8601String(),
        ], now()->addDay());

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('aiOperationalAlert.available', true)
                ->where('aiOperationalAlert.total', 0));
    }

    private function advisor(string $email): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function entrepreneurProfileFor(User $advisor, string $name, string $email): EntrepreneurProfile
    {
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'name' => $name,
            'email' => $email,
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);

        return EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => $name,
            'email' => $email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'gamification_on' => true,
        ]);
    }

    private function clientFor(User $advisor, string $name, string $dataQuality = Client::DATA_QUALITY_MEDIUM): Client
    {
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000001',
            'legal_name' => $name,
            'data_quality' => $dataQuality,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $client;
    }

    private function clientContactFor(Client $client, string $name, string $email): User
    {
        $contact = User::factory()->withTwoFactor()->create([
            'name' => $name,
            'email' => $email,
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $contact->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $contact->getKey());
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $contact->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $contact;
    }

    /**
     * @return array{0: TermsVersion, 1: TermsVersion}
     */
    private function publishedTerms(): array
    {
        $prior = TermsVersion::query()->create([
            'version' => 'dashboard-prior',
            'title' => 'Prior terms',
            'material' => true,
            'published_at' => now()->subMonth(),
        ]);
        $latest = TermsVersion::query()->create([
            'version' => 'dashboard-latest',
            'title' => 'Latest terms',
            'material' => true,
            'published_at' => now()->subDay(),
        ]);

        return [$prior, $latest];
    }

    private function acceptTerms(User $user, TermsVersion $terms, mixed $expiresAt = null): void
    {
        TermsAcceptance::query()->create([
            'user_id' => $user->getKey(),
            'terms_version_id' => $terms->getKey(),
            'accepted_at' => now()->subHours(2),
            'expires_at' => $expiresAt,
            'ip' => '127.0.0.1',
            'user_agent' => 'DashboardTest',
        ]);
    }

    private function documentFlagFor(Client $client, string $filename): DocumentVerification
    {
        $document = Document::query()->create([
            'client_id' => $client->id,
            'category' => Document::CATEGORY_FINANCIAL_STATEMENT,
            'original_filename' => $filename,
            'stored_path' => 'secure/'.$filename,
            'byte_size' => 100,
            'sha256' => hash('sha256', $filename),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);

        return DocumentVerification::query()->create([
            'document_id' => $document->id,
            'client_id' => $client->id,
            'claim_source' => 'dashboard_test',
            'context_hash' => hash('sha256', $filename.'-claim'),
            'claim_text' => 'Claim needs advisor review.',
            'outcome' => DocumentVerification::OUTCOME_ADVISORY_FLAG,
            'confidence' => 0.45,
            'explanation' => 'The evidence is incomplete.',
            'verified_at' => now(),
        ]);
    }

    private function redFlagFor(Client $client, string $headline): void
    {
        RedFlag::query()->create([
            'client_id' => $client->id,
            'source_type' => 'dashboard_test',
            'source_key' => $headline,
            'category' => RedFlag::CATEGORY_VIABILITY,
            'severity' => 'critical',
            'headline' => $headline,
            'detail' => 'Critical analysis flag for dashboard scoping.',
            'surfaced_at' => now(),
        ]);
    }

    private function prospectLead(string $name, string $email): void
    {
        ProspectLead::query()->create([
            'name' => $name,
            'email' => $email,
            'message' => 'I would like an advisory conversation.',
            'source' => 'public_contact_form',
        ]);
    }

    private function integrationSample(string $service, string $health): void
    {
        IntegrationHealthSample::query()->create([
            'service' => $service,
            'window_start' => now()->subMinutes(5),
            'window_end' => now(),
            'success_rate' => 0.99,
            'p95_latency_ms' => 250,
            'health' => $health,
        ]);
    }
}
