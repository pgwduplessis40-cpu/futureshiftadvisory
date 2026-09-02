<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ConflictDeclaration;
use App\Models\DdEngagement;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * Adds the DD browser fixture after visual dashboard checks have completed.
 * This keeps the approved dashboard baseline independent from a screen-only
 * test record while exercising the actual DD information page.
 */
final class BrowserE2eDdSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Browser E2E accounts may only be seeded in the testing environment.');
        }

        $advisor = $this->existingUser('E2E_ADVISOR_EMAIL');
        $clientUser = $this->existingUser('E2E_CLIENT_EMAIL');
        $this->call(DdSpecificQuestionnaireV2Seeder::class);

        $client = Client::query()->firstOrNew(['id' => '00000000-0000-4000-8000-000000000003']);
        $client->forceFill([
            'engagement_type' => EngagementType::DUE_DILIGENCE,
            'nzbn' => '9429000000002',
            'legal_name' => 'Browser E2E Due Diligence Client',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
            'primary_contact_user_id' => $clientUser->getKey(),
            'created_at' => BrowserE2eSeeder::fixtureTimestamp(),
            'updated_at' => BrowserE2eSeeder::fixtureTimestamp(),
        ])->save();

        foreach ([
            [$advisor, 'lead_advisor'],
            [$clientUser, 'primary_contact'],
        ] as [$user, $role]) {
            ClientTeamMember::query()->updateOrCreate(
                ['client_id' => $client->getKey(), 'user_id' => $user->getKey()],
                ['role' => $role, 'granted_modules' => [EngagementType::DUE_DILIGENCE->value]],
            );
        }

        $conflict = ConflictDeclaration::query()->updateOrCreate(
            ['client_id' => $client->getKey(), 'advisor_id' => $advisor->getKey()],
            [
                'declaration' => ['known_conflicts' => false, 'source' => 'browser_e2e_fixture'],
                'declared_at' => BrowserE2eSeeder::fixtureTimestamp(),
            ],
        );

        DdEngagement::query()->updateOrCreate(
            ['client_id' => $client->getKey(), 'target_name' => 'Browser E2E DD Target Ltd'],
            [
                'target_details' => [
                    'sector' => 'Testing',
                    'purpose' => 'Isolated browser test fixture.',
                ],
                'status' => DdEngagement::STATUS_IN_PROGRESS,
                'recommendation' => null,
                'conflict_declaration_id' => $conflict->getKey(),
                'created_by_user_id' => $advisor->getKey(),
                'disclaimer_acknowledged_at' => BrowserE2eSeeder::fixtureTimestamp(),
            ],
        );
    }

    private function existingUser(string $environment): User
    {
        $email = env($environment);

        if (! is_string($email) || trim($email) === '') {
            throw new LogicException("{$environment} must be provided by CI secrets.");
        }

        return User::query()->where('email', $email)->firstOrFail();
    }
}
