<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\InviteToken;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InviteIssuer
{
    public function __construct(private readonly AuditWriter $auditWriter) {}

    public function issue(
        string $email,
        string $targetUserType,
        string $targetRole,
        ?Authenticatable $issuedBy = null,
    ): IssuedInvite {
        $email = Str::lower(trim($email));
        $this->validateTarget($email, $targetUserType, $targetRole);

        $plainToken = Str::random(64);
        $invite = InviteToken::query()->create([
            'email' => $email,
            'target_role' => $targetRole,
            'target_user_type' => $targetUserType,
            'token_hash' => InviteToken::hashToken($plainToken),
            'expires_at' => now()->addHours((int) config('security.invite_token_ttl_hours', 72)),
            'issued_by_user_id' => $issuedBy?->getAuthIdentifier(),
        ]);
        $acceptUrl = route('invite.accept', ['token' => $plainToken]);

        Mail::raw(
            "You have been invited to Future Shift Advisory.\n\nAccept your invite: {$acceptUrl}\n",
            fn ($message) => $message
                ->to($email)
                ->subject('Future Shift Advisory invitation'),
        );

        $this->auditWriter->record(
            action: 'invite.issued',
            subject: $invite,
            actor: $issuedBy,
            after: [
                'email' => $email,
                'target_user_type' => $targetUserType,
                'target_role' => $targetRole,
                'expires_at' => $invite->expires_at?->toIso8601String(),
            ],
        );

        return new IssuedInvite($invite, $plainToken, $acceptUrl);
    }

    private function validateTarget(string $email, string $targetUserType, string $targetRole): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'A valid invitee email address is required.']);
        }

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'This email address already has an account.']);
        }

        if (! in_array($targetUserType, User::userTypes(), true)) {
            throw ValidationException::withMessages(['target_user_type' => 'The requested user type is not supported.']);
        }

        if (! in_array($targetRole, User::userTypes(), true)) {
            throw ValidationException::withMessages(['target_role' => 'The requested role is not supported.']);
        }
    }
}
