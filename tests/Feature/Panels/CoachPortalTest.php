<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\CoachSpecialisation;
use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\PanelMember;
use App\Models\Referral;
use App\Models\User;
use App\Services\Panels\Coach\CoachPanel;
use App\Services\Panels\PanelOnboarding;
use App\Services\Panels\ReferralLifecycle;
use App\Services\Pdf\PdfRenderer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

final class CoachPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');

        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });
    }

    public function test_coach_vetting_records_specialisations_memberships_and_agreement_boundary(): void
    {
        $advisor = $this->advisor();
        $coach = $this->coach('vetted-coach@example.test');
        $onboarding = app(PanelOnboarding::class);
        $member = $onboarding->submitApplication($coach, PanelMember::TYPE_COACH, [
            'trading_name' => 'Vetted Coach',
        ]);

        $member = app(CoachPanel::class)->vet(
            member: $member,
            admin: $advisor,
            specialisations: CoachSpecialisation::values(),
            profile: ['bio' => 'Executive and wellbeing coach.'],
            memberships: [['body' => 'ICF', 'level' => 'Associate']],
            vetting: ['notes' => 'References checked.'],
        );
        $agreement = $onboarding->approve($member, $advisor);

        $this->assertSame(CoachSpecialisation::values(), $member->coach_specialisations);
        $this->assertSame('ICF', $member->professional_memberships[0]['body']);
        $this->assertTrue($member->coach_vetting['admin_managed']);
        $this->assertNotNull($member->coach_vetted_at);
        $this->assertStringContainsString('no clinical mental-health', $agreement->terms['coach_clauses']['wellbeing_scope_boundary']);
        $this->assertTrue($agreement->terms['coach_clauses']['client_authorisation_required_for_key_staff']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'panel.coach_vetted',
            'subject_id' => $member->id,
        ]);
    }

    public function test_key_staff_coach_referral_requires_client_authorisation(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor();
        $coach = $this->activeCoach('key-staff-coach@example.test', [CoachSpecialisation::BUSINESS_EXECUTIVE->value]);
        $panel = app(CoachPanel::class);

        try {
            $panel->createReferral(
                client: $client,
                coach: $coach,
                advisor: $advisor,
                specialisation: CoachSpecialisation::BUSINESS_EXECUTIVE->value,
                subjectType: CoachPanel::SUBJECT_KEY_STAFF,
                payload: ['staff_name' => 'Ops Lead'],
            );
            $this->fail('Key-staff coach referrals should require client authorisation.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Key-staff coach referrals require client authorisation.', $e->getMessage());
        }

        $authorisation = $panel->authoriseKeyStaff(
            client: $client,
            authoriser: $advisor,
            staffName: 'Ops Lead',
            staffEmail: 'ops@example.test',
            purpose: 'Executive coaching support',
        );
        $referral = $panel->createReferral(
            client: $client,
            coach: $coach,
            advisor: $advisor,
            specialisation: CoachSpecialisation::BUSINESS_EXECUTIVE->value,
            subjectType: CoachPanel::SUBJECT_KEY_STAFF,
            payload: ['staff_name' => 'Ops Lead'],
            authorisation: $authorisation,
        );

        $this->assertSame(Referral::TYPE_COACH, $referral->referral_type);
        $this->assertSame(CoachPanel::SUBJECT_KEY_STAFF, $referral->referred_subject_type);
        $this->assertSame($authorisation->id, $referral->coach_referral_authorisation_id);
        $this->assertDatabaseHas('coach_referral_authorisations', [
            'id' => $authorisation->id,
            'client_id' => $client->id,
            'staff_name' => 'Ops Lead',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'coach.referral_classified',
            'subject_id' => $referral->id,
        ]);
    }

    public function test_coach_referral_stages_and_entrepreneur_subject_are_supported(): void
    {
        $advisor = $this->advisor('entrepreneur-coach-advisor@example.test');
        $coach = $this->activeCoach('entrepreneur-coach@example.test', [CoachSpecialisation::CAREER->value]);
        $entrepreneur = EntrepreneurProfile::query()->create([
            'user_id' => null,
            'assigned_advisor_id' => $advisor->getKey(),
            'invite_token_id' => null,
            'name' => 'Founder Person',
            'email' => 'founder@example.test',
            'stage' => EntrepreneurStage::INVITED,
            'concept_summary' => 'Founder needs career transition coaching.',
        ]);

        $referral = app(CoachPanel::class)->createEntrepreneurReferral(
            entrepreneur: $entrepreneur,
            coach: $coach,
            advisor: $advisor,
            specialisation: CoachSpecialisation::CAREER->value,
            payload: ['context' => 'Founder pathway'],
        );
        $lifecycle = app(ReferralLifecycle::class);
        $referral = $lifecycle->transition($referral, Referral::STAGE_COACH_REFERRAL_SENT, $advisor);
        $referral = $lifecycle->transition($referral, Referral::STAGE_COACH_ACCEPTED, $coach->user);
        $referral = $lifecycle->transition($referral, Referral::STAGE_COACHING_UNDERWAY, $coach->user);
        $referral = $lifecycle->transition($referral, Referral::STAGE_COACH_CONCLUDED, $coach->user);

        $this->assertNull($referral->client_id);
        $this->assertSame($entrepreneur->id, $referral->entrepreneur_profile_id);
        $this->assertSame(Referral::STAGE_COACH_CONCLUDED, $referral->stage);
        $this->assertNotNull($referral->closed_at);
        $this->assertContains(Referral::STAGE_COACHING_UNDERWAY, Referral::coachStages());
    }

    public function test_coach_dashboard_surfaces_panel_and_referral_actions(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor();
        $coach = $this->activeCoach('dashboard-coach@example.test', [CoachSpecialisation::LIFE->value]);
        $referral = app(CoachPanel::class)->createReferral(
            client: $client,
            coach: $coach,
            advisor: $advisor,
            specialisation: CoachSpecialisation::LIFE->value,
            subjectType: CoachPanel::SUBJECT_OWNER,
            payload: ['reason' => 'Owner resilience support'],
        );
        $referral->forceFill([
            'stage' => Referral::STAGE_ACCEPTED,
            'sent_at' => now()->subDay(),
        ])->save();

        $this->actingAsMfa($coach->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('coach/Dashboard')
                ->where('dashboard.panel.status', PanelMember::STATUS_ACTIVE)
                ->where('dashboard.referrals.0.stage', Referral::STAGE_ACCEPTED)
                ->where('dashboard.referrals.0.availableActions.0.stage', Referral::STAGE_COACHING_UNDERWAY)
                ->where('dashboard.referrals.0.stageUpdateUrl', route('coach.referrals.stage', $referral, absolute: false)));
    }

    public function test_coach_can_update_own_referral_stage_from_dashboard_action(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor();
        $coach = $this->activeCoach('dashboard-action-coach@example.test', [CoachSpecialisation::LIFE->value]);
        $referral = app(CoachPanel::class)->createReferral(
            client: $client,
            coach: $coach,
            advisor: $advisor,
            specialisation: CoachSpecialisation::LIFE->value,
            subjectType: CoachPanel::SUBJECT_OWNER,
            payload: ['reason' => 'Owner resilience support'],
        );
        $referral->forceFill([
            'stage' => Referral::STAGE_ACCEPTED,
            'sent_at' => now()->subDay(),
        ])->save();

        $this->actingAsMfa($coach->user)
            ->patch(route('coach.referrals.stage', $referral), [
                'stage' => Referral::STAGE_COACHING_UNDERWAY,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('referrals', [
            'id' => $referral->id,
            'stage' => Referral::STAGE_COACHING_UNDERWAY,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'referral.stage_changed',
            'subject_id' => $referral->id,
        ]);
    }

    /**
     * @param  array<int, string>  $specialisations
     */
    private function activeCoach(string $email, array $specialisations): PanelMember
    {
        $advisor = $this->advisor('approver-'.$email);
        $user = $this->coach($email);
        $onboarding = app(PanelOnboarding::class);
        $member = $onboarding->submitApplication($user, PanelMember::TYPE_COACH, ['fixture' => true]);
        $member = app(CoachPanel::class)->vet($member, $advisor, $specialisations);
        $agreement = $onboarding->approve($member, $advisor);
        $onboarding->signAgreement($agreement, $user);

        return $member->refresh()->load('user');
    }

    private function coach(string $email): User
    {
        $coach = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_COACH,
            'primary_role' => User::TYPE_COACH,
        ]);
        $coach->assignRole(User::TYPE_COACH);

        return $coach;
    }

    private function advisor(string $email = 'coach-portal-advisor@example.test'): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientWithAdvisor(): array
    {
        $advisor = $this->advisor('coach-client-advisor@example.test');
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Coach Client Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client];
    }
}
