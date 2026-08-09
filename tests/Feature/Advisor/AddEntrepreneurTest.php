<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EntrepreneurStage;
use App\Jobs\RefreshIdeaValidationAiReview;
use App\Mail\InvitationMail;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\InviteToken;
use App\Models\PlanAssessment;
use App\Models\RatingFramework;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Pdf\PdfRenderer;
use App\Services\Security\InviteIssuer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\MakesIdeaReviewEligible;
use Tests\TestCase;

final class AddEntrepreneurTest extends TestCase
{
    use MakesIdeaReviewEligible, RefreshDatabase;

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
        $this->assertSame(ServiceActivation::SERVICE_ENTREPRENEUR, $profile->intended_service_type);
        $this->assertSame(ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET, $profile->intended_package_scope);
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

    public function test_advisor_can_add_entrepreneur_manually_without_invite(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.create-manual'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/entrepreneurs/Create')
                ->where('mode', 'manual')
                ->where('capacity.blocked', false)
            );

        $this->actingAsMfa($advisor)
            ->post(route('advisor.entrepreneurs.store-manual'), [
                'name' => 'Manual Founder',
                'email' => 'Manual.Founder@example.com',
                'concept_summary' => 'Advisor entered profile after an offline conversation.',
            ])
            ->assertRedirect();

        $profile = EntrepreneurProfile::query()->firstOrFail();

        $this->assertSame('manual.founder@example.com', $profile->email);
        $this->assertSame('Manual Founder', $profile->name);
        $this->assertSame(EntrepreneurStage::ONBOARDING, $profile->stage);
        $this->assertSame($advisor->id, $profile->assigned_advisor_id);
        $this->assertNull($profile->invite_token_id);
        $this->assertDatabaseCount('invite_tokens', 0);
        $this->assertDatabaseHas('audit_events', ['action' => 'entrepreneur.created_manual']);
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

    public function test_advisor_can_edit_pending_invite_details_before_resending(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $issued = app(InviteIssuer::class)->issue(
            email: 'wrong-founder@example.com',
            targetUserType: User::TYPE_ENTREPRENEUR,
            targetRole: User::TYPE_ENTREPRENEUR,
            intendedServiceType: ServiceActivation::SERVICE_ENTREPRENEUR,
            intendedPackageScope: ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            issuedBy: $advisor,
        );
        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->id,
            'invite_token_id' => $issued->invite->id,
            'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'intended_package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'name' => 'Wrong Founder',
            'email' => 'wrong-founder@example.com',
            'stage' => EntrepreneurStage::INVITED,
            'concept_summary' => 'Old concept summary.',
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('entrepreneur.invite_update_url', route('advisor.entrepreneurs.invite.update', $profile, absolute: false))
                ->where('entrepreneur.intended_package_scope', ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION)
                ->where('entrepreneur.intended_package_scope_label', 'Idea Validation')
                ->where('serviceOptions.0.value', ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION)
            );

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.entrepreneurs.invite.update', $profile), [
                'name' => 'Correct Founder',
                'email' => 'Correct.Founder@example.com',
                'intended_package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
                'concept_summary' => 'Corrected concept summary.',
            ])
            ->assertRedirect(route('advisor.entrepreneurs.show', $profile, absolute: false))
            ->assertSessionHas('status', 'entrepreneur-invite-details-updated');

        $profile->refresh();
        $issued->invite->refresh();

        $this->assertSame('Correct Founder', $profile->name);
        $this->assertSame('correct.founder@example.com', $profile->email);
        $this->assertSame(ServiceActivation::SERVICE_ENTREPRENEUR, $profile->intended_service_type);
        $this->assertSame(ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET, $profile->intended_package_scope);
        $this->assertSame('Corrected concept summary.', $profile->concept_summary);
        $this->assertTrue($issued->invite->isExpired());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.invite_details_updated',
            'subject_id' => $profile->id,
        ]);
        Mail::assertNothingSent();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('entrepreneur.invite_delivery_label', 'No active invite')
                ->where('entrepreneur.invite_resend_url', route('advisor.entrepreneurs.invite.resend', $profile, absolute: false))
                ->where('entrepreneur.invite_cancel_url', null)
                ->where('entrepreneur.intended_package_scope', ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET)
                ->where('entrepreneur.intended_package_scope_label', 'Business Plan + Budget')
            );

        $this->actingAsMfa($advisor)
            ->post(route('advisor.entrepreneurs.invite.resend', $profile))
            ->assertRedirect(route('advisor.entrepreneurs.show', $profile, absolute: false))
            ->assertSessionHas('status', 'entrepreneur-invite-resent');

        $freshInvite = InviteToken::query()
            ->whereKey($profile->refresh()->invite_token_id)
            ->firstOrFail();

        $this->assertSame('correct.founder@example.com', $freshInvite->email);
        $this->assertSame(ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET, $freshInvite->intended_package_scope);
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

    public function test_advisor_can_review_and_refresh_deferred_idea_validation(): void
    {
        $this->seed(RoleSeeder::class);
        Queue::fake();
        $advisor = $this->advisor();
        $entrepreneur = User::factory()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Deferred Founder',
            'email' => 'deferred-founder@example.test',
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'concept_summary' => 'Advisor-supported business validation.',
        ]);
        $validation = IdeaValidation::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'evaluated_by_user_id' => $entrepreneur->id,
            'problem' => 'Business Advisory',
            'target_customer' => "SME's",
            'solution' => 'System that can evaluate their current state.',
            'value_proposition' => 'It supports them in growing the business.',
            'demand_signal' => "Struggling SME's",
            'revenue_model' => 'Service fee for advisory support',
            'ai_evaluation' => [
                'summary' => 'AI unavailable - analysis deferred',
                'model' => 'fake-ai-client',
                'metadata' => [
                    'degraded' => true,
                    'findings' => [[
                        'title' => 'Cost model needs current labour reference data',
                        'body' => 'The service model depends on paid coordination and suburb-level travel time.',
                        'recommended_action' => 'Build a detailed per-household cost model using real NZ labour rates (minimum wage is $23.15/hr as of April 2025), realistic travel time per suburb, and materials cost. Share this model with an advisor before proceeding to pilot.',
                    ], [
                        'title' => 'Seasonal partnership proof is needed before scaling',
                        'body' => 'Volunteer retention and partner commitments need longer-term proof before the model is scaled.',
                        'recommended_action' => 'Pilot one hub end-to-end with a real Project Leader and document volunteer retention over at least one full season (3 months minimum). Establish a written partnership agreement template with at least one church before scaling.',
                    ]],
                ],
            ],
            'viability_alerts' => [],
            'evaluated_at' => now()->subMinute(),
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/entrepreneurs/Show')
                ->where('entrepreneur.idea_validation.id', $validation->id)
                ->where('entrepreneur.idea_validation.ai_deferred', true)
                ->where('entrepreneur.idea_validation.viability_gate.status', 'red')
                ->where('entrepreneur.idea_validation.viability_gate.approval_available', false)
                ->where('entrepreneur.idea_validation.refresh_status', null)
                ->where('entrepreneur.idea_validation.refresh_stale', false)
                ->where('entrepreneur.idea_validation.problem', 'Business Advisory')
                ->where('entrepreneur.idea_validation.target_customer', "SME's")
                ->where(
                    'entrepreneur.idea_validation.proposed_change_request',
                    "Dear Deferred,\n\nThank you for the work you have put into this idea validation.\n\nYour idea shows promise, but more evidence and a more repeatable commercial model are needed before it can move into business-plan development.\n\nBefore resubmitting, please complete the short-term validation work:\n1. Build a detailed per-household cost model using current NZ wage reference data, realistic travel time per suburb, and materials cost. Share this model with an advisor before proceeding to pilot.\n\nLonger-term plan-builder evidence to prepare after the gate decision:\n1. Pilot one hub end-to-end with a real Project Leader and document volunteer retention over at least one full season (3 months minimum). Establish a written partnership agreement template with at least one church before scaling.\n\nPlease update the idea validation with the short-term evidence and resubmit it for review. Keep the longer-term items for the plan-builder or scaling work if the gate is approved.",
                )
                ->where('entrepreneur.idea_validation.refresh_url', route('advisor.entrepreneurs.idea-validations.refresh', [$profile, $validation], absolute: false))
                ->where('entrepreneur.idea_validation.request_changes_url', route('advisor.entrepreneurs.idea-validations.request-changes', [$profile, $validation], absolute: false))
                ->where('entrepreneur.idea_validation.gate_url', route('advisor.entrepreneurs.idea-validations.gate', [$profile, $validation], absolute: false)));

        $this->actingAsMfa($advisor)
            ->post(route('advisor.entrepreneurs.idea-validations.refresh', [$profile, $validation]))
            ->assertRedirect(route('advisor.entrepreneurs.show', $profile, absolute: false))
            ->assertSessionHas('status', 'entrepreneur-idea-refresh-queued');

        $validation->refresh();

        $this->assertSame('fake-ai-client', data_get($validation->ai_evaluation, 'model'));
        $this->assertSame($entrepreneur->id, $validation->evaluated_by_user_id);
        $this->assertSame('queued', data_get($validation->ai_evaluation, 'metadata.refresh_status'));
        Queue::assertPushed(
            RefreshIdeaValidationAiReview::class,
            fn (RefreshIdeaValidationAiReview $job): bool => $job->ideaValidationId === $validation->id
                && $job->advisorId === $advisor->id,
        );
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.idea_validation_refresh_queued',
            'subject_id' => $validation->id,
        ]);
    }

    public function test_advisor_can_request_idea_validation_changes(): void
    {
        $this->seed(RoleSeeder::class);
        Notification::fake();
        $advisor = $this->advisor();
        $entrepreneur = User::factory()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Needs Changes Founder',
            'email' => 'needs-changes-founder@example.test',
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'concept_summary' => 'Advisor-supported business validation.',
        ]);
        $validation = IdeaValidation::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'evaluated_by_user_id' => $entrepreneur->id,
            'problem' => 'Business Advisory',
            'target_customer' => "SME's",
            'solution' => 'System that can evaluate their current state.',
            'value_proposition' => 'It supports them in growing the business.',
            'demand_signal' => "Struggling SME's",
            'revenue_model' => 'Service fee for advisory support',
            'ai_evaluation' => [
                'summary' => 'Real problem, thin evidence.',
                'model' => 'fake-ai-client',
                'metadata' => [],
            ],
            'viability_alerts' => [],
            'evaluated_at' => now()->subMinute(),
        ]);
        $validation = $this->completedIdeaReview($validation);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.entrepreneurs.idea-validations.request-changes', [$profile, $validation]), [
                'change_request_note' => 'Please capture one more customer experiment and resubmit the idea validation.',
            ])
            ->assertRedirect(route('advisor.entrepreneurs.show', $profile, absolute: false))
            ->assertSessionHas('status', 'entrepreneur-idea-changes-requested');

        $validation->refresh();

        $this->assertSame('changes_requested', data_get($validation->ai_evaluation, 'metadata.advisor_gate_status'));
        $this->assertSame(
            'Please capture one more customer experiment and resubmit the idea validation.',
            data_get($validation->ai_evaluation, 'metadata.change_request_note'),
        );
        $this->assertSame(EntrepreneurStage::IDEA_VALIDATION, $profile->refresh()->stage);
        $this->assertDatabaseHas('message_threads', [
            'entrepreneur_profile_id' => $profile->id,
            'subject' => 'Idea validation changes requested',
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/entrepreneurs/Show')
                ->where('entrepreneur.idea_validation.id', $validation->id)
                ->where('entrepreneur.idea_validation.advisor_gate_status', 'changes_requested')
                ->where('entrepreneur.idea_validation.viability_gate.status', 'amber')
                ->where('entrepreneur.idea_validation.viability_gate.label', 'Amber - changes requested')
                ->where('entrepreneur.idea_validation.viability_gate.approval_available', false)
            );
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
        $pdfRenderer = new class implements PdfRenderer
        {
            public string $html = '';

            public function render(string $html): string
            {
                $this->html = $html;

                return '%PDF-1.7 entrepreneur-plan-preview';
            }
        };
        $this->app->instance(PdfRenderer::class, $pdfRenderer);

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
                ->missing('documentUploadUrl')
            );

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/entrepreneurs/Show')
                ->where('entrepreneur.messages.url', route('advisor.entrepreneurs.messages.index', $profile, absolute: false))
                ->where('entrepreneur.latest_plan.preview_pdf_url', route('advisor.entrepreneurs.plans.preview', [$profile, $plan], absolute: false))
                ->where('entrepreneur.latest_plan.budget_pdf_url', null)
                ->where('entrepreneur.latest_plan.latest_assessment.url', route('advisor.entrepreneurs.assessments.show', [$profile, $assessment], absolute: false))
                ->where('entrepreneur.latest_plan.latest_assessment.weighted_score', 86.3)
                ->where('entrepreneur.documents.0.url', route('advisor.entrepreneurs.documents.show', [$profile, $document], absolute: false))
            );

        $response = $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.plans.preview', [$profile, $plan]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        self::assertStringContainsString('inline; filename=', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('Business plan preview', $pdfRenderer->html);
        self::assertStringContainsString('Portal Founder', $pdfRenderer->html);

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

    public function test_quarantined_document_is_not_presented_as_an_openable_advisor_link(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $entrepreneur = User::factory()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => 'Quarantined Upload Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::BUILDING_PHASE_1,
        ]);
        Storage::fake('secure_local');
        Storage::disk('secure_local')->put('documents/quarantined.docx', 'unsafe until scanned');
        $document = Document::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'category' => Document::CATEGORY_PLAN_ATTACHMENT,
            'original_filename' => 'quarantined.docx',
            'stored_path' => 'documents/quarantined.docx',
            'byte_size' => 20,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'sha256' => hash('sha256', 'unsafe until scanned'),
            'uploaded_by_user_id' => $entrepreneur->getKey(),
            'scanner_result' => Document::SCANNER_ERROR,
            'scanner_payload' => [
                'result' => 'error',
                'message' => 'ClamAV daemon unavailable.',
            ],
        ]);
        Document::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'category' => Document::CATEGORY_PLAN_ATTACHMENT,
            'original_filename' => 'quarantined.docx',
            'stored_path' => 'documents/quarantined-duplicate.docx',
            'byte_size' => 20,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'sha256' => $document->sha256,
            'uploaded_by_user_id' => $entrepreneur->getKey(),
            'scanner_result' => Document::SCANNER_ERROR,
            'scanner_payload' => $document->scanner_payload,
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('entrepreneur.documents', 1)
                ->where('entrepreneur.documents.0.scanner_result', Document::SCANNER_ERROR)
                ->where('entrepreneur.documents.0.scanner_message', 'ClamAV daemon unavailable.')
                ->where('entrepreneur.documents.0.url', null));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.documents.show', [$profile, $document]))
            ->assertNotFound();
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
