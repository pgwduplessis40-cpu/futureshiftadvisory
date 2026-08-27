<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\NpoBoardMember;
use App\Models\NpoEngagement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;
use LogicException;

/**
 * Creates only isolated browser-test identities. All credentials and TOTP
 * secrets are injected by CI; this class intentionally provides no defaults.
 */
final class BrowserE2eSeeder extends Seeder
{
    /**
     * The approved browser baselines were captured in UTC. Keeping this
     * fixture timestamp fixed prevents date labels from making visual
     * approval fail on a later CI day.
     */
    private const FIXTURE_TIMESTAMP = '2026-08-26 22:15:00';

    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Browser E2E accounts may only be seeded in the testing environment.');
        }

        $this->call(RoleSeeder::class);

        $advisor = $this->upsertUser('ADVISOR', User::TYPE_ADVISOR);
        $clientUser = $this->upsertUser('CLIENT', User::TYPE_CLIENT_PRIMARY);
        $npoUser = $this->upsertUser('NPO', User::TYPE_NPO_BOARD_MEMBER);
        $client = $this->upsertClient(
            id: '00000000-0000-4000-8000-000000000001',
            legalName: 'Browser E2E Isolated Client',
            engagementType: EngagementType::STANDARD_ADVISORY,
            advisor: $advisor,
            contact: $clientUser,
        );

        foreach ([
            [$advisor, 'lead_advisor'],
            [$clientUser, 'primary_contact'],
        ] as [$user, $role]) {
            ClientTeamMember::query()->updateOrCreate(
                ['client_id' => $client->getKey(), 'user_id' => $user->getKey()],
                ['role' => $role, 'granted_modules' => [EngagementType::STANDARD_ADVISORY->value]],
            );
        }

        $npoClient = $this->upsertClient(
            id: '00000000-0000-4000-8000-000000000002',
            legalName: 'Browser E2E NPO Client',
            engagementType: EngagementType::NPO,
            advisor: $advisor,
            contact: $npoUser,
        );
        $engagement = NpoEngagement::query()->updateOrCreate(
            ['client_id' => $npoClient->getKey()],
            [
                'sub_type' => NpoEngagementSubType::StandardNpo,
                'legal_structure' => NpoLegalStructure::RegisteredCharity,
                'created_by_user_id' => $advisor->getKey(),
                'updated_by_user_id' => $advisor->getKey(),
            ],
        );
        NpoBoardMember::query()->updateOrCreate(
            ['npo_engagement_id' => $engagement->getKey(), 'user_id' => $npoUser->getKey()],
            [
                'client_id' => $npoClient->getKey(),
                'treasurer' => true,
                'active' => true,
                'created_by_user_id' => $advisor->getKey(),
            ],
        );
    }

    private function upsertClient(string $id, string $legalName, EngagementType $engagementType, User $advisor, User $contact): Client
    {
        $client = Client::query()->firstOrNew(['id' => $id]);
        $client->forceFill([
            'engagement_type' => $engagementType,
            'nzbn' => $id === '00000000-0000-4000-8000-000000000001' ? '9429000000000' : '9429000000001',
            'legal_name' => $legalName,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
            'primary_contact_user_id' => $contact->getKey(),
            'created_at' => $this->fixtureTimestamp(),
            'updated_at' => $this->fixtureTimestamp(),
        ])->save();

        return $client;
    }

    private function upsertUser(string $prefix, string $type): User
    {
        $email = $this->required("E2E_{$prefix}_EMAIL");
        $password = $this->required("E2E_{$prefix}_PASSWORD");
        $totpSecret = $this->required("E2E_{$prefix}_MFA_SECRET");
        $encrypter = Fortify::currentEncrypter();

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => "Browser E2E {$prefix}",
            'email' => $email,
            'email_verified_at' => $this->fixtureTimestamp(),
            'password' => Hash::make($password),
            'user_type' => $type,
            'primary_role' => $type,
            // Fortify decrypts these with serialized encryption. Do not use
            // encryptString(), whose string-only payload cannot be verified
            // by the two-factor login pipeline.
            'two_factor_secret' => $encrypter->encrypt($totpSecret),
            'two_factor_recovery_codes' => $encrypter->encrypt(json_encode([
                'browser-e2e-recovery-code',
            ], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => $this->fixtureTimestamp(),
            'mfa_enabled_at' => $this->fixtureTimestamp(),
            'mfa_method' => User::MFA_METHOD_TOTP,
            'created_at' => $this->fixtureTimestamp(),
            'updated_at' => $this->fixtureTimestamp(),
        ])->save();
        $user->syncRoles([$type]);

        return $user;
    }

    private function required(string $name): string
    {
        $value = env($name);

        if (! is_string($value) || trim($value) === '') {
            throw new LogicException("{$name} must be provided by CI secrets.");
        }

        return $value;
    }

    private function fixtureTimestamp(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d H:i:s',
            self::FIXTURE_TIMESTAMP,
            'UTC',
        );
    }
}
