<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\InviteToken;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Security\MfaChallenger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class InviteAcceptController extends Controller
{
    public function __construct(private readonly AuditWriter $auditWriter) {}

    public function show(string $token): Response
    {
        $invite = $this->usableInvite($token);

        return Inertia::render('auth/invite-accept', [
            'token' => $token,
            'email' => $invite->email,
            'targetRole' => $invite->target_role,
            'targetUserType' => $invite->target_user_type,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invite = $this->usableInvite($token);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $invite->email,
            'email_verified_at' => now(),
            'password' => $validated['password'],
            'user_type' => $invite->target_user_type,
            'primary_role' => $invite->target_role,
            'last_password_set_at' => now(),
        ]);

        $invite->markAccepted($user);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put([
            'fsa.invite_flow' => true,
            'auth.password_confirmed_at' => now()->getTimestamp(),
            MfaChallenger::SESSION_USER_ID => (string) $user->getAuthIdentifier(),
        ]);

        $this->auditWriter->record(
            action: 'invite.accepted',
            subject: $invite,
            actor: $user,
            after: [
                'accepted_by_user_id' => $user->getKey(),
                'target_user_type' => $invite->target_user_type,
                'target_role' => $invite->target_role,
            ],
        );

        return redirect()->route('mfa.setup');
    }

    private function usableInvite(string $token): InviteToken
    {
        $invite = InviteToken::query()
            ->where('token_hash', InviteToken::hashToken($token))
            ->firstOrFail();

        abort_unless($invite->isUsable(), 404);

        return $invite;
    }
}
