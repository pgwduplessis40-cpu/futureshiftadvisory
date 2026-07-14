<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\PortalOfflineSyncRecord;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\ScanResult;
use App\Services\Portal\OnboardingWizard;
use App\Services\Security\StepUpEvaluator;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OfflineSyncHardeningTest extends TestCase
{
    use RefreshDatabase;

    private string $secureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secureRoot = storage_path('framework/testing/portal-offline-sync');
        File::deleteDirectory($this->secureRoot);
        Config::set('filesystems.disks.secure_local.root', $this->secureRoot);
        Storage::forgetDisk('secure_local');

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('secure_local');
        File::deleteDirectory($this->secureRoot);

        parent::tearDown();
    }

    public function test_sync_onboarding_submit_returns_json_and_replays_cached_response(): void
    {
        [$user, $client] = $this->clientUserWithClient(state: $this->questionnaireStepState());

        $headers = $this->syncHeaders($client, 'questionnaire-key', accept: 'text/html, application/xhtml+xml');
        $body = ['questionnaire_set_acknowledged' => true];

        $first = $this->actingAsMfa($user)
            ->withHeaders($headers)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]), $body)
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status_slug', 'onboarding-step-saved');

        $this->assertDatabaseCount('portal_offline_sync_records', 1);
        $this->assertDatabaseCount('audit_events', 1);

        $this->actingAsMfa($user)
            ->withHeaders($headers)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]), $body)
            ->assertOk()
            ->assertExactJson($first->json());

        $this->assertDatabaseCount('portal_offline_sync_records', 1);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_sync_upload_uses_queued_client_scans_once_and_conflicts_on_changed_content(): void
    {
        $scanner = $this->bindCountingScanner();
        [$user, $queuedClient] = $this->clientUserWithClient();
        $latestClient = $this->attachClient($user, 'Latest Client Limited');

        $headers = $this->syncHeaders($queuedClient, 'upload-key');

        $first = $this->actingAsMfa($user)
            ->withHeaders($headers)
            ->post(route('portal.documents.store'), $this->uploadPayload('cashflow.pdf', 'stable payload'))
            ->assertCreated();

        $documentId = $first->json('document.id');
        $this->assertSame(1, $scanner->calls);
        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'client_id' => $queuedClient->id,
        ]);
        $this->assertDatabaseMissing('documents', [
            'id' => $documentId,
            'client_id' => $latestClient->id,
        ]);

        $this->actingAsMfa($user)
            ->withHeaders($headers)
            ->post(route('portal.documents.store'), $this->uploadPayload('cashflow.pdf', 'stable payload'))
            ->assertCreated()
            ->assertJsonPath('document.id', $documentId);

        $this->assertSame(1, $scanner->calls);
        $this->assertDatabaseCount('documents', 1);

        $this->actingAsMfa($user)
            ->withHeaders($headers)
            ->post(route('portal.documents.store'), $this->uploadPayload('cashflow.pdf', 'changed payload'))
            ->assertConflict()
            ->assertJsonPath('message', 'Offline sync idempotency key was reused with a different payload.');

        $this->assertSame(1, $scanner->calls);
        $this->assertDatabaseCount('documents', 1);
    }

    public function test_same_idempotency_key_is_scoped_by_queued_client(): void
    {
        $scanner = $this->bindCountingScanner();
        [$user, $firstClient] = $this->clientUserWithClient();
        $secondClient = $this->attachClient($user, 'Second Client Limited');

        $this->actingAsMfa($user)
            ->withHeaders($this->syncHeaders($firstClient, 'shared-key'))
            ->post(route('portal.documents.store'), $this->uploadPayload('shared.pdf', 'same bytes'))
            ->assertCreated();

        $this->actingAsMfa($user)
            ->withHeaders($this->syncHeaders($secondClient, 'shared-key'))
            ->post(route('portal.documents.store'), $this->uploadPayload('shared.pdf', 'same bytes'))
            ->assertCreated();

        $this->assertSame(2, $scanner->calls);
        $this->assertDatabaseCount('documents', 2);
        $this->assertSame(2, PortalOfflineSyncRecord::query()->where('idempotency_key', 'shared-key')->count());
    }

    public function test_sync_rejects_missing_or_inaccessible_queued_client_without_retargeting(): void
    {
        [$user, $client] = $this->clientUserWithClient();
        $this->attachClient($user, 'Still Accessible Limited');

        $this->actingAsMfa($user)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Portal-Offline-Sync' => '1',
                'X-Idempotency-Key' => 'legacy-key',
            ])
            ->post(route('portal.documents.store'), $this->uploadPayload('legacy.pdf', 'legacy payload'))
            ->assertUnprocessable();

        ClientTeamMember::query()
            ->where('client_id', $client->getKey())
            ->where('user_id', $user->getKey())
            ->delete();

        $this->actingAsMfa($user)
            ->withHeaders($this->syncHeaders($client, 'revoked-key'))
            ->post(route('portal.documents.store'), $this->uploadPayload('revoked.pdf', 'revoked payload'))
            ->assertForbidden();

        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('portal_offline_sync_records', 0);
    }

    public function test_sync_auth_flow_redirects_become_json_for_html_accept_requests(): void
    {
        [$user, $client] = $this->clientUserWithClient();
        $headers = $this->syncHeaders($client, 'auth-key', accept: 'text/html, application/xhtml+xml');

        $this->withHeaders($headers)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_WELCOME]), ['acknowledged' => true])
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('message', 'Unauthenticated.');

        $mfaPending = User::factory()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $mfaPending->assignRole(User::TYPE_CLIENT_PRIMARY);
        $mfaClient = $this->attachClient($mfaPending, 'MFA Pending Limited');

        $this->actingAs($mfaPending)
            ->withHeaders($this->syncHeaders($mfaClient, 'mfa-key', accept: 'text/html, application/xhtml+xml'))
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_WELCOME]), ['acknowledged' => true])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Portal offline sync requires an active authenticated session.');

        TermsVersion::query()->create([
            'version' => 'offline-sync',
            'title' => 'Offline Sync Terms',
            'material' => true,
            'published_at' => now(),
            'notice_period_days' => 30,
        ]);

        $this->actingAsMfa($user)
            ->withHeaders($headers)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_WELCOME]), ['acknowledged' => true])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Portal offline sync requires an active authenticated session.');

        $this->actingAsMfa($user)
            ->withSession([StepUpEvaluator::SESSION_LAST_ACTIVITY_AT => now()->subHours(2)->getTimestamp()])
            ->withHeaders($headers)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_WELCOME]), ['acknowledged' => true])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Portal offline sync requires an active authenticated session.');
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUserWithClient(?array $state = null): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        return [$user, $this->attachClient($user, 'Queued Client Limited', $state)];
    }

    private function attachClient(User $user, string $name, ?array $state = null): Client
    {
        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => null,
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
            'status' => ClientStatus::ACTIVE,
            'onboarding_wizard_state' => $state,
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $client;
    }

    /**
     * @return array<string, string>
     */
    private function syncHeaders(Client $client, string $key, string $accept = 'application/json'): array
    {
        return [
            'Accept' => $accept,
            'X-Portal-Offline-Sync' => '1',
            'X-Idempotency-Key' => $key,
            'X-Portal-Client-Id' => (string) $client->getKey(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadPayload(string $name, string $contents): array
    {
        if (str_ends_with($name, '.pdf') && ! str_starts_with($contents, '%PDF-')) {
            $contents = "%PDF-1.4\n".$contents;
        }

        return [
            'file' => UploadedFile::fake()->createWithContent($name, $contents),
            'category' => Document::CATEGORY_PLAN_ATTACHMENT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function questionnaireStepState(): array
    {
        return [
            'journey_version' => 2,
            'current_step' => 4,
            'completed_steps' => [
                OnboardingWizard::STEP_WELCOME,
                OnboardingWizard::STEP_GOALS,
                OnboardingWizard::STEP_WEBSITE,
            ],
            'steps' => [],
        ];
    }

    private function bindCountingScanner(): object
    {
        $scanner = new class implements FileScanner
        {
            public int $calls = 0;

            public function scan(mixed $stream): ScanResult
            {
                $this->calls++;

                return ScanResult::clean(['engine' => 'offline-sync-test']);
            }
        };

        $this->app->instance(FileScanner::class, $scanner);

        return $scanner;
    }
}
