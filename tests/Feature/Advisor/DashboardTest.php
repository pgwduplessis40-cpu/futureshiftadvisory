<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\IntegrationHealthSample;
use App\Models\ProspectLead;
use App\Models\RedFlag;
use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $otherAdvisor = $this->advisor('other-advisor@example.test');
        $client = $this->clientFor($advisor, 'Scoped Health Limited', Client::DATA_QUALITY_LOW);
        $otherClient = $this->clientFor($otherAdvisor, 'Other Advisor Limited', Client::DATA_QUALITY_INSUFFICIENT);
        $contact = $this->clientContactFor($client, 'Client Contact', 'contact@example.test');
        $otherContact = $this->clientContactFor($otherClient, 'Other Contact', 'other-contact@example.test');

        [$priorTerms, $latestTerms] = $this->publishedTerms();
        $this->acceptTerms($advisor, $latestTerms);
        $this->acceptTerms($contact, $priorTerms, now()->subDay());
        $this->acceptTerms($otherContact, $priorTerms, now()->subDay());

        $this->documentFlagFor($client, 'scoped-support.pdf');
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
                ->where('documentVerificationFlags.0.client_name', 'Scoped Health Limited')
                ->where('documentVerificationFlags.0.document_name', 'scoped-support.pdf')
                ->where('pendingTermsReacceptance.total', 1)
                ->where('pendingTermsReacceptance.items.0.user_name', 'Client Contact')
                ->where('prospectInbox.total', 2)
                ->where('prospectInbox.triage_enabled', true)
                ->where('integrationHealth.summary.total', 1)
                ->where('integrationHealth.services.0.service', 'nzbn'));
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

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('entrepreneurReviews.summary.total', 2)
                ->where('entrepreneurReviews.summary.idea_validations', 1)
                ->where('entrepreneurReviews.summary.business_plans', 1)
                ->where('entrepreneurReviews.items.0.id', $validation->id)
                ->where('entrepreneurReviews.items.0.type', 'idea_validation')
                ->where('entrepreneurReviews.items.0.label', 'Idea validation')
                ->where('entrepreneurReviews.items.0.entrepreneur_name', 'Wessel Du Plessis')
                ->where('entrepreneurReviews.items.0.action_label', 'Review idea')
                ->where('entrepreneurReviews.items.1.id', $plan->id)
                ->where('entrepreneurReviews.items.1.type', 'business_plan')
                ->where('entrepreneurReviews.items.1.label', 'Business plan')
                ->where('entrepreneurReviews.items.1.entrepreneur_name', 'Wessel Du Plessis')
                ->where('entrepreneurReviews.items.1.action_label', 'Finalise review')
                ->has('entrepreneurReviews.items', 2));
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

    private function documentFlagFor(Client $client, string $filename): void
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

        DocumentVerification::query()->create([
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
