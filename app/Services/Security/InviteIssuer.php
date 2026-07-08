<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Mail\InvitationMail;
use App\Models\InviteToken;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
        ?string $intendedServiceType = null,
        ?string $intendedPackageScope = null,
        ?Authenticatable $issuedBy = null,
        bool $deliver = false,
    ): IssuedInvite {
        $email = Str::lower(trim($email));
        [$intendedServiceType, $intendedPackageScope] = $this->normaliseServiceIntent($intendedServiceType, $intendedPackageScope);
        $this->validateTarget($email, $targetUserType, $targetRole, $intendedServiceType);

        $plainToken = Str::random(64);
        $invite = InviteToken::query()->create([
            'email' => $email,
            'target_role' => $targetRole,
            'target_user_type' => $targetUserType,
            'intended_service_type' => $intendedServiceType,
            'intended_package_scope' => $intendedPackageScope,
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
                'intended_service_type' => $intendedServiceType,
                'intended_package_scope' => $intendedPackageScope,
                'service_intent_label' => $invite->serviceIntentLabel(),
                'expires_at' => $invite->expires_at?->toIso8601String(),
                'delivery_mode' => $deliver ? 'app_mail' : 'token_issued',
            ],
        );

        if ($deliver) {
            $this->deliver($draft, $issuedBy);
        }

        return new IssuedInvite(
            invite: $invite,
            plainToken: $plainToken,
            acceptUrl: (string) $draft['accept_url'],
            emailSubject: (string) $draft['subject'],
            emailBody: (string) $draft['body'],
        );
    }

    /**
     * @return array{accept_url: string, to: string, subject: string, body: string}|null
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
        ];
    }

    private function validateTarget(
        string $email,
        string $targetUserType,
        string $targetRole,
        ?string $intendedServiceType,
    ): void {
        if ($intendedServiceType === ServiceActivation::SERVICE_ENTREPRENEUR && $targetUserType !== User::TYPE_ENTREPRENEUR) {
            throw ValidationException::withMessages([
                'target_user_type' => 'Business Idea invites must create an entrepreneur account.',
            ]);
        }

        if ($intendedServiceType === ServiceActivation::SERVICE_DUE_DILIGENCE && $targetUserType !== User::TYPE_CLIENT_PRIMARY) {
            throw ValidationException::withMessages([
                'target_user_type' => 'Buying a Business invites must create a client-primary account.',
            ]);
        }

        $this->validateUserTarget($email, $targetUserType, $targetRole);
    }

    private function validateUserTarget(string $email, string $targetUserType, string $targetRole): void
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

    /**
     * @return array{0:string|null,1:string|null}
     */
    private function normaliseServiceIntent(?string $serviceType, ?string $packageScope): array
    {
        $serviceType = is_string($serviceType) ? trim($serviceType) : null;
        $packageScope = is_string($packageScope) ? trim($packageScope) : null;

        if ($serviceType === '') {
            $serviceType = null;
        }

        if ($packageScope === '') {
            $packageScope = null;
        }

        if ($serviceType === null) {
            return [null, null];
        }

        if (! in_array($serviceType, [ServiceActivation::SERVICE_ENTREPRENEUR, ServiceActivation::SERVICE_DUE_DILIGENCE], true)) {
            throw ValidationException::withMessages(['intended_service_type' => 'Choose a supported invite access path.']);
        }

        return [
            $serviceType,
            match ($serviceType) {
                ServiceActivation::SERVICE_ENTREPRENEUR => ServiceRatePackage::normaliseEntrepreneurScope($packageScope),
                ServiceActivation::SERVICE_DUE_DILIGENCE => $packageScope !== null
                    ? ServiceRatePackage::normaliseDueDiligenceScope($packageScope)
                    : null,
                default => null,
            },
        ];
    }

    private function inviteBody(InviteToken $invite, string $acceptUrl): string
    {
        $expiresAt = $invite->expires_at?->format('j M Y, g:i A T') ?? 'the expiry date shown in Future Shift Advisory';
        $accountLabel = $this->accountLabel((string) $invite->target_user_type);
        $serviceIntent = $invite->serviceIntentLabel();
        $serviceLine = $serviceIntent !== null
            ? ["Your advisor selected the {$serviceIntent} access path for this invitation.", '']
            : [];

        return implode("\n", [
            'Hello,',
            '',
            "Future Shift Advisory has invited you to create your {$accountLabel}.",
            '',
            ...$serviceLine,
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

    /**
     * @param  array{accept_url: string, to: string, subject: string, body: string}  $draft
     */
    private function deliver(array $draft, ?Authenticatable $issuedBy): void
    {
        $replyToEmail = $issuedBy instanceof User ? $issuedBy->email : null;
        $replyToName = $issuedBy instanceof User ? $issuedBy->name : null;
        $send = fn (): mixed => Mail::to((string) $draft['to'])->send(new InvitationMail(
            subjectLine: (string) $draft['subject'],
            bodyText: (string) $draft['body'],
            replyToEmail: is_string($replyToEmail) ? $replyToEmail : null,
            replyToName: is_string($replyToName) ? $replyToName : null,
        ));

        if (app()->runningUnitTests()) {
            $send();

            return;
        }

        DB::afterCommit($send);
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
