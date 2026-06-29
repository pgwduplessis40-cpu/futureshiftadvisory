<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\InviteToken;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InviteIssuer
{
    private const INVITE_SUBJECT = 'Future Shift Advisory invitation';

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
            'token_envelope' => Crypt::encryptString($plainToken),
            'expires_at' => now()->addHours((int) config('security.invite_token_ttl_hours', 72)),
            'issued_by_user_id' => $issuedBy?->getAuthIdentifier(),
        ]);
        $draft = $this->draftFor($invite, $plainToken);

        $this->auditWriter->record(
            action: 'invite.issued',
            subject: $invite,
            actor: $issuedBy,
            after: [
                'email' => $email,
                'target_user_type' => $targetUserType,
                'target_role' => $targetRole,
                'expires_at' => $invite->expires_at?->toIso8601String(),
                'delivery_mode' => 'manual_outlook',
            ],
        );

        return new IssuedInvite(
            invite: $invite,
            plainToken: $plainToken,
            acceptUrl: (string) $draft['accept_url'],
            emailSubject: (string) $draft['subject'],
            emailBody: (string) $draft['body'],
        );
    }

    /**
     * @return array{accept_url: string, to: string, subject: string, body: string, outlook_url: string, mailto_url: string}|null
     */
    public function draftFor(?InviteToken $invite, ?string $plainToken = null): ?array
    {
        if (! $invite instanceof InviteToken || ! $invite->isUsable()) {
            return null;
        }

        if ($plainToken === null) {
            $envelope = $invite->token_envelope ?? null;

            if (! is_string($envelope) || trim($envelope) === '') {
                return null;
            }

            try {
                $plainToken = Crypt::decryptString($envelope);
            } catch (DecryptException) {
                return null;
            }
        }

        $acceptUrl = route('invite.accept', ['token' => $plainToken]);
        $to = (string) $invite->email;
        $body = $this->inviteBody($invite, $acceptUrl);

        return [
            'accept_url' => $acceptUrl,
            'to' => $to,
            'subject' => self::INVITE_SUBJECT,
            'body' => $body,
            'outlook_url' => $this->outlookComposeUrl($to, self::INVITE_SUBJECT, $body),
            'mailto_url' => $this->mailtoUrl($to, self::INVITE_SUBJECT, $body),
        ];
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

    private function inviteBody(InviteToken $invite, string $acceptUrl): string
    {
        $expiresAt = $invite->expires_at?->format('j M Y, g:i A T') ?? 'the expiry date shown in Future Shift Advisory';
        $accountLabel = $this->accountLabel((string) $invite->target_user_type);

        return implode("\n", [
            'Hello,',
            '',
            "Future Shift Advisory has invited you to create your {$accountLabel}.",
            '',
            'Please use this secure invitation link to set up your account:',
            $acceptUrl,
            '',
            "This link expires on {$expiresAt}.",
            '',
            'If the link has expired, reply to this email and we will issue a fresh invitation.',
            '',
            'Kind regards,',
            'Future Shift Advisory',
        ])."\n";
    }

    private function outlookComposeUrl(string $to, string $subject, string $body): string
    {
        return 'https://outlook.office.com/mail/deeplink/compose?'.http_build_query([
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function mailtoUrl(string $to, string $subject, string $body): string
    {
        return 'mailto:'.$to.'?'.http_build_query([
            'subject' => $subject,
            'body' => $body,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function accountLabel(string $targetUserType): string
    {
        return match ($targetUserType) {
            User::TYPE_ENTREPRENEUR => 'entrepreneur account',
            User::TYPE_BROKER => 'broker account',
            User::TYPE_COACH => 'coach account',
            User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM => 'client account',
            User::TYPE_NPO_BOARD_MEMBER => 'NPO board member account',
            default => 'Future Shift Advisory account',
        };
    }
}
