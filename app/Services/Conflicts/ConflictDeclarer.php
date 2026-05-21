<?php

declare(strict_types=1);

namespace App\Services\Conflicts;

use App\Models\Client;
use App\Models\ConflictDeclaration;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Validation\ValidationException;

final class ConflictDeclarer
{
    public const CLIENT_CREATION = 'client_creation';

    public const DUE_DILIGENCE = 'due_diligence';

    public const BROKER_REFERRAL = 'broker_referral';

    public const COACH_REFERRAL = 'coach_referral';

    public const FRESH_FOR_DAYS = 30;

    public function __construct(private readonly AuditWriter $auditWriter) {}

    /**
     * @return array<int, string>
     */
    public static function referralTypes(): array
    {
        return [
            self::CLIENT_CREATION,
            self::DUE_DILIGENCE,
            self::BROKER_REFERRAL,
            self::COACH_REFERRAL,
        ];
    }

    public function declare(
        User $advisor,
        Client $client,
        string $referralType,
        bool $existingRelationship,
        ?string $details = null,
    ): ConflictDeclaration {
        $this->ensureDeclarable($advisor, $referralType);

        $declaration = ConflictDeclaration::query()->create([
            'client_id' => $client->getKey(),
            'advisor_id' => $advisor->getKey(),
            'declaration' => [
                'declared' => true,
                'referral_type' => $referralType,
                'existing_relationship' => $existingRelationship,
                'details' => $this->nullableDetails($details),
            ],
            'declared_at' => now(),
        ]);

        $this->auditWriter->record('conflict.declared', subject: $declaration, actor: $advisor, after: [
            'client_id' => $client->getKey(),
            'referral_type' => $referralType,
            'existing_relationship' => $existingRelationship,
        ]);

        return $declaration;
    }

    public function require(
        User $advisor,
        Client $client,
        string $referralType,
        int $freshForDays = self::FRESH_FOR_DAYS,
    ): ConflictDeclaration {
        $this->ensureDeclarable($advisor, $referralType);

        $declaration = $this->latestFor($advisor, $client, $referralType);

        if (! $declaration instanceof ConflictDeclaration || ! $declaration->isFreshFor($freshForDays)) {
            throw new ConflictDeclarationRequiredException($referralType, $declaration);
        }

        return $declaration;
    }

    public function latestFor(User $advisor, Client $client, string $referralType): ?ConflictDeclaration
    {
        return ConflictDeclaration::query()
            ->where('client_id', $client->getKey())
            ->where('advisor_id', $advisor->getKey())
            ->where('declaration->referral_type', $referralType)
            ->latest('declared_at')
            ->first();
    }

    private function ensureDeclarable(User $advisor, string $referralType): void
    {
        if (! in_array($referralType, self::referralTypes(), true)) {
            throw ValidationException::withMessages([
                'conflict.referral_type' => 'Choose a supported declaration type.',
            ]);
        }

        if (! in_array($advisor->user_type, [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN], true)) {
            throw ValidationException::withMessages([
                'advisor' => 'Only advisors can record conflict declarations.',
            ]);
        }
    }

    private function nullableDetails(?string $details): ?string
    {
        $details = is_string($details) ? trim($details) : null;

        return $details === '' ? null : $details;
    }
}
