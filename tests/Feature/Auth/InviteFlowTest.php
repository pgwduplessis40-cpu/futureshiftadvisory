<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\InviteToken;
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
        $this->assertNotSame($issued->plainToken, $issued->invite->token_hash);
        $this->assertSame(64, strlen($issued->invite->token_hash));
        $this->assertTrue($issued->invite->expires_at->greaterThan(now()->addHours(71)));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'invite.issued',
            'subject_id' => $issued->invite->id,
        ]);
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
            'password' => 'A-secure-password-123',
            'password_confirmation' => 'A-secure-password-123',
        ]);

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

        $response->assertRedirect(route('mfa.setup', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(User::TYPE_CLIENT_PRIMARY, $user->user_type);
        $this->assertSame(User::TYPE_CLIENT_PRIMARY, $user->primary_role);
        $this->assertNotNull($user->last_password_set_at);
        $this->assertNotNull($issued->invite->refresh()->accepted_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'invite.accepted',
            'subject_id' => $issued->invite->id,
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
            'password' => 'A-secure-password-123',
            'password_confirmation' => 'A-secure-password-123',
        ]);

        auth()->logout();

        $this->get(route('invite.accept', $issued->plainToken))->assertNotFound();
        $this->post(route('invite.store', $issued->plainToken), [
            'name' => 'Replay Person',
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

        $this->get(route('invite.accept', $plainToken))->assertNotFound();
    }
}
