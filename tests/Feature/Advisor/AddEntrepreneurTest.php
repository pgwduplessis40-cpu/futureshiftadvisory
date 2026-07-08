<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EntrepreneurStage;
use App\Mail\InvitationMail;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\InviteToken;
use App\Models\PlanAssessment;
use App\Models\RatingFramework;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Security\InviteIssuer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AddEntrepreneurTest extends TestCase
{
    use RefreshDatabase;

    public function test_advisor_can_create_entrepreneur_profile_and_issue_invite(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.entrepreneurs.store'), [
                'name' => 'Aroha Founder',
                'email' => 'Aroha.Founder@example.com',
                'concept_summary' => 'Circular retail analytics for regional stores.',
                'intended_package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
                'stage' => EntrepreneurStage::READINESS->value,
            ])
            ->assertRedirect();

        $profile = EntrepreneurProfile::query()->firstOrFail();
        $invite = InviteToken::query()->firstOrFail();

        $this->assertSame('aroha.founder@example.com', $profile->email);
        $this->assertSame('Aroha Founder', $profile->name);
        $this->assertSame(EntrepreneurStage::INVITED, $profile->stage);
        $this->assertSame($advisor->id, $profile->assigned_advisor_id);
        $this->assertSame($invite->id, $profile->invite_token_id);
        $this->assertTrue($profile->gamification_on);
        $this->assertSame(User::TYPE_ENTREPRENEUR, $invite->target_user_type);
        $this->assertSame(User::TYPE_ENTREPRENEUR, $invite->target_role);
        $this->assertSame(ServiceActivation::SERVICE_ENTREPRENEUR, $invite->intended_service_type);
        $this->assertSame(ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET, $invite->intended_package_scope);
        $this->assertNotEmpty($invite->token_envelope);
        $this->assertDatabaseHas('audit_events', ['action' => 'entrepreneur.created']);
        Mail::assertSent(InvitationMail::class, 1);
    }

    public function test_advisor_must_choose_invite_service_when_creating_entrepreneur(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.entrepreneurs.store'), [
                'name' => 'Scope Missing',
                'email' => 'scope-missing@example.com',
                'concept_summary' => 'Valid concept, missing access selection.',
            ])
            ->assertSessionHasErrors('intended_package_scope');

        $this->assertDatabaseCount('entrepreneur_profiles', 0);
        $this->assertDatabaseCount('invite_tokens', 0);
        Mail::assertNothingSent();
    }

    public function test_advisor_can_resend_pending_entrepreneur_invite(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $issued = app(InviteIssuer::class)->issue(
            email: 'founder@example.com',
            targetUserType: User::TYPE_ENTREPRENEUR,
            targetRole: User::TYPE_ENTREPRENEUR,
            issuedBy: $advisor,
        );
        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->id,
            'invite_token_id' => $issued->invite->id,
            'name' => 'Founder Person',
            'email' => 'founder@example.com',
            'stage' => EntrepreneurStage::INVITED,
            'concept_summary' => 'Specialist onboarding concept.',
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('entrepreneur.invite_delivery_label', 'Email sent')
                ->where('entrepreneur.invite_resend_url', route('advisor.entrepreneurs.invite.resend', $profile, absolute: false))
                ->where('entrepreneur.invite_cancel_url', route('advisor.entrepreneurs.invite.cancel', $profile, absolute: false))
            );

        $this->actingAsMfa($advisor)
            ->post(route('advisor.entrepreneurs.invite.resend', $profile))
            ->assertRedirect(route('advisor.entrepreneurs.show', $profile, absolute: false))
            ->assertSessionHas('status', 'entrepreneur-invite-resent');

        $profile->refresh();
        $issued->invite->refresh();

        $this->assertNotSame($issued->invite->id, $profile->invite_token_id);
        $this->assertSame(EntrepreneurStage::INVITED, $profile->stage);
        $this->assertTrue($issued->invite->isExpired());
        $this->assertDatabaseCount('invite_tokens', 2);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.invite_resent',
            'subject_id' => $profile->id,
        ]);
        Mail::assertSent(InvitationMail::class, 1);
    }

    public function test_advisor_can_cancel_pending_entrepreneur_invite(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $issued = app(InviteIssuer::class)->issue(
            email: 'cancel-founder@example.com',
            targetUserType: User::TYPE_ENTREPRENEUR,
            targetRole: User::TYPE_ENTREPRENEUR,
            issuedBy: $advisor,
        );
        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->id,
            'invite_token_id' => $issued->invite->id,
            'name' => 'Cancel Founder',
            'email' => 'cancel-founder@example.com',
            'stage' => EntrepreneurStage::INVITED,
            'concept_summary' => 'Pending invite to cancel.',
        ]);

        $this->actingAsMfa($advisor)
            ->delete(route('advisor.entrepreneurs.invite.cancel', $profile))
            ->assertRedirect(route('advisor.entrepreneurs.show', $profile, absolute: false))
            ->assertSessionHas('status', 'entrepreneur-invite-cancelled');

        $profile->refresh();
        $issued->invite->refresh();

        $this->assertSame(EntrepreneurStage::CANCELLED, $profile->stage);
        $this->assertTrue($issued->invite->isExpired());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.invite_cancelled',
            'subject_id' => $profile->id,
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('entrepreneur.stage', EntrepreneurStage::CANCELLED->value)
                ->where('entrepreneur.stage_label', 'Cancelled')
                ->where('entrepreneur.invite_resend_url', route('advisor.entrepreneurs.invite.resend', $profile, absolute: false))
                ->where('entrepreneur.invite_cancel_url', null)
            );
    }

    public function test_accepted_entrepreneur_invite_cannot_be_resent_or_cancelled(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $issued = app(InviteIssuer::class)->issue(
            email: 'accepted@example.com',
            targetUserType: User::TYPE_ENTREPRENEUR,
            targetRole: User::TYPE_ENTREPRENEUR,
            issuedBy: $advisor,
        );
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => 'accepted@example.com',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $issued->invite->forceFill([
            'accepted_at' => now(),
            'accepted_by_user_id' => $entrepreneur->id,
        ])->save();
        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->id,
            'invite_token_id' => $issued->invite->id,
            'user_id' => $entrepreneur->id,
            'name' => 'Accepted Founder',
            'email' => 'accepted@example.com',
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'Accepted onboarding concept.',
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('entrepreneur.invite_resend_url', null)
                ->where('entrepreneur.invite_cancel_url', null)
            );

        $this->actingAsMfa($advisor)
            ->from(route('advisor.entrepreneurs.show', $profile))
            ->post(route('advisor.entrepreneurs.invite.resend', $profile))
            ->assertRedirect(route('advisor.entrepreneurs.show', $profile, absolute: false))
            ->assertSessionHasErrors('invite');

        $this->actingAsMfa($advisor)
            ->from(route('advisor.entrepreneurs.show', $profile))
            ->delete(route('advisor.entrepreneurs.invite.cancel', $profile))
            ->assertRedirect(route('advisor.entrepreneurs.show', $profile, absolute: false))
            ->assertSessionHasErrors('invite');

        $this->assertSame($issued->invite->id, $profile->refresh()->invite_token_id);
        $this->assertSame(EntrepreneurStage::ONBOARDING, $profile->stage);
        $this->assertDatabaseCount('invite_tokens', 1);
    }

    public function test_accepted_invite_state_is_visible_when_profile_stage_is_still_invited(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $issued = app(InviteIssuer::class)->issue(
            email: 'accepted-stale@example.com',
            targetUserType: User::TYPE_ENTREPRENEUR,
            targetRole: User::TYPE_ENTREPRENEUR,
            issuedBy: $advisor,
        );
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => 'accepted-stale@example.com',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $issued->invite->markAccepted($entrepreneur);

        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->id,
            'invite_token_id' => $issued->invite->id,
            'name' => 'Accepted Stale Founder',
            'email' => 'accepted-stale@example.com',
            'stage' => EntrepreneurStage::INVITED,
            'concept_summary' => 'Invite accepted while the workflow stage is still stale.',
            'gamification_on' => true,
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/entrepreneurs/Index')
                ->where('entrepreneurs.0.id', $profile->id)
                ->where('entrepreneurs.0.stage', EntrepreneurStage::INVITED->value)
                ->where('entrepreneurs.0.stage_label', 'Invite accepted')
            );

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/entrepreneurs/Show')
                ->where('entrepreneur.stage', EntrepreneurStage::INVITED->value)
                ->where('entrepreneur.stage_label', 'Invite accepted')
                ->whereNot('entrepreneur.invite_accepted_at', null)
                ->where('entrepreneur.invite_resend_url', null)
                ->where('entrepreneur.invite_cancel_url', null)
                ->where('entrepreneur.gamification.current_level.stage_label', 'Invite accepted')
                ->where('entrepreneur.gamification.current_level.label', 'Invite accepted')
            );
    }

    public function test_accepting_invite_links_profile_and_moves_to_onboarding(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $issued = app(InviteIssuer::class)->issue(
            email: 'founder@example.com',
            targetUserType: User::TYPE_ENTREPRENEUR,
            targetRole: User::TYPE_ENTREPRENEUR,
            issuedBy: $advisor,
        );
        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->id,
            'invite_token_id' => $issued->invite->id,
            'name' => 'Founder Person',
            'email' => 'founder@example.com',
            'stage' => EntrepreneurStage::INVITED,
            'concept_summary' => 'Specialist onboarding concept.',
        ]);

        $this->post(route('invite.store', $issued->plainToken), [
            'name' => 'Founder Person',
            'mobile_phone' => '+64 21 123 4567',
            'password' => 'A-secure-password-123',
            'password_confirmation' => 'A-secure-password-123',
        ])->assertRedirect(route('mfa.setup', absolute: false));

        $user = User::query()->where('email', 'founder@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(User::TYPE_ENTREPRENEUR, $user->user_type);
        $this->assertSame($user->id, $profile->refresh()->user_id);
        $this->assertSame(EntrepreneurStage::ONBOARDING, $profile->stage);
        $this->assertDatabaseHas('audit_events', ['action' => 'entrepreneur.onboarding_started']);
    }

    public function test_capacity_warning_is_exposed_at_twenty_four_active_entrepreneurs(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $this->createProfiles($advisor, 24);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/entrepreneurs/Create')
                ->where('serviceOptions.0.value', ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION)
                ->where('serviceOptions.1.value', ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET)
                ->where('serviceOptions.2.value', ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO)
                ->where('capacity.active_count', 24)
                ->where('capacity.warning_threshold', 24)
                ->where('capacity.warning', true)
                ->where('capacity.blocked', false)
            );
    }

    public function test_capacity_hard_blocks_at_thirty_active_entrepreneurs(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $this->createProfiles($advisor, 30);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.entrepreneurs.store'), [
                'name' => 'Blocked Founder',
                'email' => 'blocked@example.com',
                'concept_summary' => 'Should not be invited.',
                'intended_package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO,
            ])
            ->assertSessionHasErrors('capacity');

        $this->assertDatabaseCount('entrepreneur_profiles', 30);
        $this->assertDatabaseCount('invite_tokens', 0);
        Mail::assertNothingSent();
    }

    public function test_entrepreneur_dashboard_redirects_to_actionable_dashboard(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);

        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Portal Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'Portal placeholder concept.',
        ]);
        $framework = RatingFramework::query()->create([
            'version' => 1,
            'status' => RatingFramework::STATUS_PUBLISHED,
            'production_ready' => true,
            'grade_bands' => RatingFramework::DEFAULT_GRADE_BANDS,
            'published_at' => now(),
            'published_by_user_id' => $advisor->getKey(),
            'created_by_user_id' => $advisor->getKey(),
        ]);
        $criterion = $framework->criteria()->create([
            'number' => 1,
            'name' => 'Market proof',
            'weight' => 100,
            'descriptors' => ['strong' => 'Market evidence is clear.'],
            'is_placeholder' => false,
        ]);
        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'title' => 'Portal founder plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_FINALISED,
            'current_phase' => 1,
            'created_by_user_id' => $advisor->getKey(),
            'completed_at' => now(),
        ]);
        $assessment = PlanAssessment::query()->create([
            'business_plan_id' => $plan->id,
            'round' => 1,
            'rating_framework_id' => $framework->id,
            'ai_scores' => [[
                'criterion_id' => $criterion->id,
                'criterion_number' => 1,
                'criterion_name' => 'Market proof',
                'score' => 86.3,
                'rationale' => 'Evidence supports the market.',
            ]],
            'advisor_scores' => [],
            'mentor_notes' => ['overall_visible' => 'Strong evidence base.'],
            'document_support' => ['attached_document_count' => 1],
            'overall_grade' => 'strong',
            'finalised_at' => now(),
            'finalised_by_user_id' => $advisor->getKey(),
        ]);
        AdvisoryReadinessSignal::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'business_plan_id' => $plan->id,
            'plan_assessment_id' => $assessment->id,
            'score' => 86.3,
            'surfaced_at' => now(),
            'advisor_notified_at' => now(),
        ]);
        Storage::fake('secure_local');
        Storage::disk('secure_local')->put('documents/market-proof.pdf', 'market proof');
        $document = Document::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'category' => Document::CATEGORY_PLAN_ATTACHMENT,
            'original_filename' => 'market-proof.pdf',
            'stored_path' => 'documents/market-proof.pdf',
            'byte_size' => 12,
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', 'market proof'),
            'uploaded_by_user_id' => $entrepreneur->getKey(),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);

        $this->actingAsMfa($entrepreneur)
            ->get(route('dashboard'))
            ->assertRedirect(route('portal.entrepreneur.dashboard', absolute: false));

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/entrepreneur/Dashboard')
                ->where('profile.stage', EntrepreneurStage::ONBOARDING->value)
                ->where('profile.name', 'Portal Founder')
                ->where('profile.latest_plan.assessment_count', 1)
                ->where('profile.latest_plan.completed_assessment_count', 1)
                ->where('profile.latest_plan.latest_assessment.url', route('portal.entrepreneur.assessments.show', $assessment, absolute: false))
                ->where('profile.latest_documents.0.url', route('portal.documents.show', $document, absolute: false))
                ->where('profile.advisory_readiness_signal.criteria.0.name', 'Market proof')
                ->where('profile.advisory_readiness_signal.explanation', fn (string $value): bool => str_contains($value, 'weighted total'))
                ->where('profile.message_summary.threads_count', 0)
                ->where('messagesUrl', route('portal.messages.index', absolute: false))
                ->where('documentUploadUrl', route('portal.documents.store', absolute: false))
            );

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/entrepreneurs/Show')
                ->where('entrepreneur.messages.url', route('advisor.entrepreneurs.messages.index', $profile, absolute: false))
                ->where('entrepreneur.latest_plan.latest_assessment.url', route('advisor.entrepreneurs.assessments.show', [$profile, $assessment], absolute: false))
                ->where('entrepreneur.latest_plan.latest_assessment.weighted_score', 86.3)
                ->where('entrepreneur.documents.0.url', route('advisor.entrepreneurs.documents.show', [$profile, $document], absolute: false))
            );

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.assessments.show', [$profile, $assessment]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/entrepreneur/Assessment')
                ->where('backLabel', 'Entrepreneur')
                ->where('assessment.weighted_score', 86.3)
                ->where('assessment.criteria.0.name', 'Market proof')
            );

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.documents.show', [$profile, $document]))
            ->assertOk();

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/entrepreneur/Assessment')
                ->where('assessment.weighted_score', 86.3)
                ->where('assessment.criteria.0.name', 'Market proof')
                ->where('assessment.document_support.attached_document_count', 1)
            );
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

    private function createProfiles(User $advisor, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            EntrepreneurProfile::query()->create([
                'assigned_advisor_id' => $advisor->id,
                'name' => "Founder {$i}",
                'email' => "founder{$i}@example.com",
                'stage' => EntrepreneurStage::INVITED,
            ]);
        }
    }
}
