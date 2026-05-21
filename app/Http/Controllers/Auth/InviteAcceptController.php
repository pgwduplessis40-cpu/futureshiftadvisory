<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\EntrepreneurStage;
use App\Http\Controllers\Controller;
use App\Models\EntrepreneurProfile;
use App\Models\InviteToken;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Security\MfaChallenger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

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

        $user = DB::transaction(function () use ($invite, $validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $invite->email,
                'email_verified_at' => now(),
                'password' => $validated['password'],
                'user_type' => $invite->target_user_type,
                'primary_role' => $invite->target_role,
                'last_password_set_at' => now(),
            ]);

            if (Role::query()->where('name', $invite->target_role)->where('guard_name', 'web')->exists()) {
                $user->assignRole($invite->target_role);
            }

            $invite->markAccepted($user);
            $profile = $this->linkEntrepreneurProfile($invite, $user);

            $this->auditWriter->record(
                action: 'invite.accepted',
                subject: $invite,
                actor: $user,
                after: [
                    'accepted_by_user_id' => $user->getKey(),
                    'target_user_type' => $invite->target_user_type,
                    'target_role' => $invite->target_role,
                    'entrepreneur_profile_id' => $profile?->getKey(),
                ],
            );

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put([
            'fsa.invite_flow' => true,
            'auth.password_confirmed_at' => now()->getTimestamp(),
            MfaChallenger::SESSION_USER_ID => (string) $user->getAuthIdentifier(),
        ]);

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

    private function linkEntrepreneurProfile(InviteToken $invite, User $user): ?EntrepreneurProfile
    {
        if ($invite->target_user_type !== User::TYPE_ENTREPRENEUR) {
            return null;
        }

        $profile = EntrepreneurProfile::query()
            ->where('invite_token_id', $invite->getKey())
            ->first();

        if (! $profile instanceof EntrepreneurProfile || $profile->user_id !== null) {
            return $profile;
        }

        $profile->forceFill([
            'user_id' => $user->getKey(),
            'stage' => EntrepreneurStage::ONBOARDING,
        ])->save();

        $this->auditWriter->record(
            action: 'entrepreneur.onboarding_started',
            subject: $profile,
            actor: $user,
            after: [
                'entrepreneur_profile_id' => $profile->getKey(),
                'stage' => EntrepreneurStage::ONBOARDING->value,
                'user_id' => $user->getKey(),
            ],
        );

        return $profile;
    }
}
