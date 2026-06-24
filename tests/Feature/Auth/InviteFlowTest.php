<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\EntrepreneurStage;
use App\Models\EntrepreneurProfile;
use App\Models\InviteToken;
use App\Models\PanelMember;
use App\Models\User;
use App\Services\Security\InviteIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class InviteFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_issuer_stores_only_token_hash_and_sets_expiry(): void
    {
        Mail::fake();

        $issued = app(InviteIssuer::class)->issue(
            email: 'new.client@example.com',
            targetUserType: User::TYPE_CLIENT_PRIMARY,
            targetRole: User::TYPE_CLIENT_PRIMARY,
            issuedBy: User::factory()->superAdmin()->create(),
        );

        $this->assertNotEmpty($issued->plainToken);
        $this->assertStringContainsString($issued->plainToken, $issued->acceptUrl);
        $this->assertSame('Future Shift Advisory invitation', $issued->emailSubject);
        $this->assertStringContainsString($issued->acceptUrl, $issued->emailBody);
        $this->assertNotSame($issued->plainToken, $issued->invite->token_hash);
        $this->assertSame(64, strlen($issued->invite->token_hash));
        $this->assertNotEmpty($issued->invite->token_envelope);
        $this->assertTrue($issued->invite->expires_at->greaterThan(now()->addHours(167)));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'invite.issued',
            'subject_id' => $issued->invite->id,
        ]);
        Mail::assertNothingSent();
    }

    public function test_accepting_invite_creates_user_marks_token_used_and_redirects_to_mfa_setup(): void
    {
        Mail::fake();

        $issued = app(InviteIssuer::class)->issue(
            email: 'owner@example.com',
            targetUserType: User::TYPE_CLIENT_PRIMARY,
            targetRole: User::TYPE_CLIENT_PRIMARY,
        );

        $this->get(route('invite.accept', $issued->plainToken))->assertOk();

        $response = $this->post(route('invite.store', $issued->plainToken), [
            'name' => 'Owner Person',
            'mobile_phone' => '+64 21 123 4567',
            'password' => 'A-secure-password-123',
            'password_confirmation' => 'A-secure-password-123',
        ]);

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

        $response->assertRedirect(route('mfa.setup', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(User::TYPE_CLIENT_PRIMARY, $user->user_type);
        $this->assertSame(User::TYPE_CLIENT_PRIMARY, $user->primary_role);
        $this->assertSame('+64 21 123 4567', $user->mobile_phone);
        $this->assertNotNull($user->last_password_set_at);
        $this->assertNotNull($issued->invite->refresh()->accepted_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'invite.accepted',
            'subject_id' => $issued->invite->id,
        ]);
    }

    public function test_accepting_panel_invite_links_the_panel_member_record(): void
    {
        Mail::fake();

        $issued = app(InviteIssuer::class)->issue(
            email: 'panel-broker@example.com',
            targetUserType: User::TYPE_BROKER,
            targetRole: User::TYPE_BROKER,
        );

        $member = PanelMember::query()->create([
            'invite_token_id' => $issued->invite->getKey(),
            'panel_type' => PanelMember::TYPE_BROKER,
            'status' => PanelMember::STATUS_INVITED,
            'application' => [
                'company' => 'Panel Broker Invite',
                'broker_name' => 'Panel Broker',
                'industry' => 'Business insurance',
            ],
        ]);

        $this->post(route('invite.store', $issued->plainToken), [
            'name' => 'Panel Broker',
            'mobile_phone' => '+64 21 123 4567',
            'password' => 'A-secure-password-123',
            'password_confirmation' => 'A-secure-password-123',
        ])->assertRedirect(route('mfa.setup', absolute: false));

        $user = User::query()->where('email', 'panel-broker@example.com')->firstOrFail();

        $this->assertSame((string) $user->getKey(), (string) $member->refresh()->user_id);
        $this->assertSame(PanelMember::STATUS_INVITED, $member->status);
    }

    public function test_entrepreneur_login_reconciles_pending_invite_profile(): void
    {
        Mail::fake();

        $issued = app(InviteIssuer::class)->issue(
            email: 'tania@example.test',
            targetUserType: User::TYPE_ENTREPRENEUR,
            targetRole: User::TYPE_ENTREPRENEUR,
        );
        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->getKey(),
            'invite_token_id' => $issued->invite->getKey(),
            'name' => 'Tania Hassounia',
            'email' => 'tania@example.test',
            'stage' => EntrepreneurStage::INVITED,
            'concept_summary' => 'Invite should reconcile on login.',
        ]);
        $user = User::factory()->create([
            'name' => 'Tania Hassounia',
            'email' => 'tania@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);

        $this->post(route('login.store'), [
            'email' => 'tania@example.test',
            'password' => 'password',
        ])->assertRedirect(route('mfa.setup', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->getKey(), $profile->refresh()->user_id);
        $this->assertSame(EntrepreneurStage::ONBOARDING, $profile->stage);
        $this->assertNotNull($issued->invite->refresh()->accepted_at);
        $this->assertSame($user->getKey(), $issued->invite->accepted_by_user_id);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.onboarding_started',
            'subject_id' => $profile->id,
        ]);
    }

    public function test_invite_tokens_are_one_shot(): void
    {
        Mail::fake();

        $issued = app(InviteIssuer::class)->issue(
            email: 'client@example.com',
            targetUserType: User::TYPE_CLIENT_PRIMARY,
            targetRole: User::TYPE_CLIENT_PRIMARY,
        );

        $this->post(route('invite.store', $issued->plainToken), [
            'name' => 'Client Person',
            'mobile_phone' => '+64 21 123 4567',
            'password' => 'A-secure-password-123',
            'password_confirmation' => 'A-secure-password-123',
        ]);

        auth()->logout();

        $this->get(route('invite.accept', $issued->plainToken))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/invite-expired')
                ->where('email', 'client@example.com')
                ->where('isAccepted', true));
        $this->post(route('invite.store', $issued->plainToken), [
            'name' => 'Replay Person',
            'mobile_phone' => '+64 21 222 3333',
            'password' => 'A-secure-password-123',
            'password_confirmation' => 'A-secure-password-123',
        ])->assertNotFound();
    }

    public function test_expired_invite_token_is_rejected(): void
    {
        $plainToken = 'expired-token';
        InviteToken::query()->create([
            'email' => 'expired@example.com',
            'target_role' => User::TYPE_CLIENT_PRIMARY,
            'target_user_type' => User::TYPE_CLIENT_PRIMARY,
            'token_hash' => InviteToken::hashToken($plainToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('invite.accept', $plainToken))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/invite-expired')
                ->where('email', 'expired@example.com'));
    }
}
