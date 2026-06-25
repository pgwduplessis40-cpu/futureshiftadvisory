<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\EntrepreneurStage;
use App\Http\Controllers\Controller;
use App\Models\EntrepreneurProfile;
use App\Models\InviteToken;
use App\Models\PanelMember;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Security\MfaChallenger;
use App\Support\RequestContext;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

final class InviteAcceptController extends Controller
{
    public function __construct(
        private readonly AuditWriter $auditWriter,
        private readonly RequestContext $requestContext,
    ) {}

    public function show(string $token): Response
    {
        $invite = $this->inviteForToken($token);
        $plainToken = $token;

        if (! $invite->isUsable()) {
            $replacement = $this->replacementInviteFor($invite);

            if ($replacement instanceof InviteToken) {
                $replacementToken = $this->plainTokenFor($replacement);

                if ($replacementToken !== null) {
                    $this->auditWriter->record(
                        action: 'invite.replacement_viewed',
                        subject: $replacement,
                        after: [
                            'expired_invite_token_id' => $invite->getKey(),
                            'email' => $replacement->email,
                            'expires_at' => $replacement->expires_at?->toIso8601String(),
                        ],
                    );

                    $invite = $replacement;
                    $plainToken = $replacementToken;
                }
            }
        }

        if (! $invite->isUsable()) {
            $this->auditWriter->record(
                action: 'invite.expired_or_used_viewed',
                subject: $invite,
                after: [
                    'email' => $invite->email,
                    'expired' => $invite->isExpired(),
                    'accepted' => $invite->isAccepted(),
                ],
            );

            return Inertia::render('auth/invite-expired', [
                'email' => $invite->email,
                'expiredAt' => $invite->expires_at?->toIso8601String(),
                'acceptedAt' => $invite->accepted_at?->toIso8601String(),
                'isAccepted' => $invite->isAccepted(),
            ]);
        }

        return Inertia::render('auth/invite-accept', [
            'token' => $plainToken,
            'email' => $invite->email,
            'targetRole' => $invite->target_role,
            'targetUserType' => $invite->target_user_type,
            'expiresAt' => $invite->expires_at?->toIso8601String(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invite = $this->usableInvite($token);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile_phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\\-\\s]{7,40}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $this->requestContext->withSystemContext(
            fn (): User => DB::transaction(function () use ($invite, $validated): User {
                $user = $this->activationUser($invite);

                $user->forceFill([
                    'name' => $validated['name'],
                    'email' => Str::lower(trim((string) $invite->email)),
                    'mobile_phone' => $validated['mobile_phone'],
                    'email_verified_at' => now(),
                    'password' => $validated['password'],
                    'user_type' => $invite->target_user_type,
                    'primary_role' => $invite->target_role,
                    'last_password_set_at' => now(),
                ])->save();

                if (Role::query()->where('name', $invite->target_role)->where('guard_name', 'web')->exists()) {
                    $user->assignRole($invite->target_role);
                }

                $invite->markAccepted($user);
                $profile = $this->linkEntrepreneurProfile($invite, $user);
                $panelMember = $this->linkPanelMember($invite, $user);

                $this->auditWriter->record(
                    action: 'invite.accepted',
                    subject: $invite,
                    actor: $user,
                    after: [
                        'accepted_by_user_id' => $user->getKey(),
                        'target_user_type' => $invite->target_user_type,
                        'target_role' => $invite->target_role,
                        'mobile_phone_captured' => true,
                        'entrepreneur_profile_id' => $profile?->getKey(),
                        'panel_member_id' => $panelMember?->getKey(),
                    ],
                );

                return $user;
            })
        );

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put([
            'fsa.invite_flow' => true,
            'auth.password_confirmed_at' => now()->getTimestamp(),
            MfaChallenger::SESSION_USER_ID => (string) $user->getAuthIdentifier(),
        ]);

        return redirect()->route('mfa.setup');
    }

    private function activationUser(InviteToken $invite): User
    {
        $email = Str::lower(trim((string) $invite->email));
        $existing = User::query()
            ->whereRaw('lower(trim(email)) = ?', [$email])
            ->lockForUpdate()
            ->first();

        if (! $existing instanceof User) {
            return new User;
        }

        $existingUserType = (string) ($existing->user_type ?? '');

        if (! in_array($existingUserType, ['', $invite->target_user_type], true)) {
            throw ValidationException::withMessages([
                'email' => 'This invitation email is already attached to a different account type. Ask your advisor to issue a fresh invitation to the correct email address.',
            ]);
        }

        return $existing;
    }

    private function usableInvite(string $token): InviteToken
    {
        $invite = $this->inviteForToken($token);

        if (! $invite->isUsable()) {
            $replacement = $this->replacementInviteFor($invite);

            if ($replacement instanceof InviteToken) {
                return $replacement;
            }
        }

        abort_unless($invite->isUsable(), 404);

        return $invite;
    }

    private function inviteForToken(string $token): InviteToken
    {
        return InviteToken::query()
            ->where('token_hash', InviteToken::hashToken($token))
            ->firstOrFail();
    }

    private function replacementInviteFor(InviteToken $invite): ?InviteToken
    {
        if ($invite->isAccepted() || ! $invite->isExpired()) {
            return null;
        }

        return InviteToken::query()
            ->whereKeyNot($invite->getKey())
            ->where('email', $invite->email)
            ->where('target_role', $invite->target_role)
            ->where('target_user_type', $invite->target_user_type)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->latest('created_at')
            ->first();
    }

    private function plainTokenFor(InviteToken $invite): ?string
    {
        $envelope = $invite->token_envelope ?? null;

        if (! is_string($envelope) || trim($envelope) === '') {
            return null;
        }

        try {
            return Crypt::decryptString($envelope);
        } catch (DecryptException) {
            return null;
        }
    }

    private function linkEntrepreneurProfile(InviteToken $invite, User $user): ?EntrepreneurProfile
    {
        if ($invite->target_user_type !== User::TYPE_ENTREPRENEUR) {
            return null;
        }

        $profile = EntrepreneurProfile::query()
            ->where('invite_token_id', $invite->getKey())
            ->first()
            ?? EntrepreneurProfile::query()
                ->whereNull('user_id')
                ->whereRaw('lower(email) = ?', [strtolower((string) $invite->email)])
                ->latest()
                ->first();

        if (! $profile instanceof EntrepreneurProfile) {
            return null;
        }

        $updates = [
            'user_id' => $user->getKey(),
            'invite_token_id' => $invite->getKey(),
        ];
        $stage = $profile->ensureStageIsValid(EntrepreneurStage::ONBOARDING);
        if (in_array($stage, [EntrepreneurStage::INVITED, EntrepreneurStage::CANCELLED], true)) {
            $updates['stage'] = EntrepreneurStage::ONBOARDING;
            $stage = EntrepreneurStage::ONBOARDING;
        }

        $profile->forceFill($updates)->save();

        $this->auditWriter->record(
            action: 'entrepreneur.onboarding_started',
            subject: $profile,
            actor: $user,
            after: [
                'entrepreneur_profile_id' => $profile->getKey(),
                'stage' => $stage->value,
                'user_id' => $user->getKey(),
            ],
        );

        return $profile;
    }

    private function linkPanelMember(InviteToken $invite, User $user): ?PanelMember
    {
        if (! in_array($invite->target_user_type, PanelMember::panelTypes(), true)) {
            return null;
        }

        $member = PanelMember::query()
            ->where('invite_token_id', $invite->getKey())
            ->where('panel_type', $invite->target_user_type)
            ->first()
            ?? PanelMember::query()
                ->where('panel_type', $invite->target_user_type)
                ->where(function ($query) use ($invite, $user): void {
                    $query
                        ->where('user_id', $user->getKey())
                        ->orWhere(function ($query) use ($invite): void {
                            $query
                                ->whereNull('user_id')
                                ->whereHas('inviteToken', fn ($inviteQuery) => $inviteQuery
                                    ->where('target_user_type', $invite->target_user_type)
                                    ->whereRaw('lower(email) = ?', [strtolower((string) $invite->email)]));
                        });
                })
                ->latest('updated_at')
                ->first();

        if (! $member instanceof PanelMember) {
            return null;
        }

        if ($member->user_id !== null && (string) $member->user_id !== (string) $user->getKey()) {
            return $member;
        }

        $member->forceFill([
            'user_id' => $user->getKey(),
            'invite_token_id' => $invite->getKey(),
        ])->save();

        return $member->refresh();
    }
}
