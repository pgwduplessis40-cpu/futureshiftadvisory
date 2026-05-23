<?php

declare(strict_types=1);

namespace Tests\Feature\Dd;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\DdEngagement;
use App\Models\Document;
use App\Models\User;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Dd\DataRoom;
use App\Services\Dd\DdOnboarding;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\ScanResult;
use App\Support\RequestContext;
use Database\Seeders\DdSpecificQuestionnaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DdDataRoomTest extends TestCase
{
    use RefreshDatabase;

    private string $secureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secureRoot = storage_path('framework/testing/dd-data-room');
        File::deleteDirectory($this->secureRoot);
        Config::set('filesystems.disks.secure_local.root', $this->secureRoot);
        Storage::forgetDisk('secure_local');

        $this->seed(RoleSeeder::class);
        $this->seed(DdSpecificQuestionnaireSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('secure_local');
        File::deleteDirectory($this->secureRoot);

        parent::tearDown();
    }

    public function test_guest_upload_scans_and_writes_to_the_linked_workstream_folder(): void
    {
        $scanner = $this->bindScanner(ScanResult::clean(['engine' => 'dd-test-scanner']));
        [$advisor, $engagement] = $this->ddEngagement();
        $issued = app(DataRoom::class)->issueGuestLink(
            engagement: $engagement,
            actor: $advisor,
            workstream: 'legal',
            folder: 'Vendor Documents',
            guestEmail: 'vendor@example.test',
        );

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post($issued['upload_url'], [
                'guest_name' => 'Vendor Person',
                'guest_email' => 'vendor@example.test',
                'file' => UploadedFile::fake()->createWithContent('lease.txt', 'Lease evidence and assignment terms.'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data_room_item.workstream', 'legal')
            ->assertJsonPath('data_room_item.folder', 'vendor_documents')
            ->assertJsonPath('data_room_item.artifact_type', Document::CATEGORY_DD_ARTIFACT)
            ->assertJsonPath('data_room_item.document.category', Document::CATEGORY_DD_ARTIFACT)
            ->assertJsonPath('data_room_item.document.scanner_result', Document::SCANNER_CLEAN);

        $itemId = $response->json('data_room_item.id');
        $documentId = $response->json('data_room_item.document.id');

        $this->assertSame(1, $scanner->calls);
        $this->assertStringNotContainsString('stored_path', $response->getContent());
        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'client_id' => $engagement->client_id,
            'category' => Document::CATEGORY_DD_ARTIFACT,
            'scanner_result' => Document::SCANNER_CLEAN,
            'uploaded_by_user_id' => null,
        ]);
        $this->assertDatabaseHas('dd_data_room_items', [
            'id' => $itemId,
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->id,
            'document_id' => $documentId,
            'workstream' => 'legal',
            'folder' => 'vendor_documents',
            'artifact_type' => Document::CATEGORY_DD_ARTIFACT,
            'source' => 'guest_upload',
            'dd_guest_link_id' => $issued['link']->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'dd.guest_upload_received',
            'subject_id' => $itemId,
        ]);
    }

    public function test_guest_link_has_no_data_room_view_surface(): void
    {
        [$advisor, $engagement] = $this->ddEngagement('no-view-dd-advisor@example.test');
        $issued = app(DataRoom::class)->issueGuestLink($engagement, $advisor, 'financial');

        $this->getJson($issued['upload_url'])->assertStatus(405);
    }

    public function test_revoked_guest_link_is_rejected_immediately(): void
    {
        $scanner = $this->bindScanner(ScanResult::clean(['engine' => 'dd-test-scanner']));
        [$advisor, $engagement] = $this->ddEngagement('revoked-dd-advisor@example.test');
        $issued = app(DataRoom::class)->issueGuestLink($engagement, $advisor, 'tax');

        app(DataRoom::class)->revokeGuestLink($issued['link'], $advisor);

        $this
            ->withHeader('Accept', 'application/json')
            ->post($issued['upload_url'], [
                'file' => UploadedFile::fake()->createWithContent('tax.txt', 'Tax schedule.'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('token');

        $this->assertSame(0, $scanner->calls);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('dd_data_room_items', 0);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'dd.guest_link_revoked',
            'subject_id' => $issued['link']->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'dd.guest_upload_rejected',
            'subject_id' => $issued['link']->id,
        ]);
    }

    public function test_infected_guest_upload_is_rejected_before_data_room_item_creation(): void
    {
        $scanner = $this->bindScanner(ScanResult::infected('Eicar-Test-Signature', ['engine' => 'dd-test-scanner']));
        [$advisor, $engagement] = $this->ddEngagement('infected-dd-advisor@example.test');
        $issued = app(DataRoom::class)->issueGuestLink($engagement, $advisor, 'operational');

        $this
            ->withHeader('Accept', 'application/json')
            ->post($issued['upload_url'], [
                'file' => UploadedFile::fake()->createWithContent('eicar.txt', 'EICAR fixture'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame(1, $scanner->calls);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('dd_data_room_items', 0);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'document.upload_rejected.infected',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'dd.guest_upload_rejected',
            'subject_id' => $issued['link']->id,
        ]);
    }

    /**
     * @return object{calls:int}
     */
    private function bindScanner(ScanResult $result): object
    {
        $scanner = new class($result) implements FileScanner
        {
            public int $calls = 0;

            public function __construct(private readonly ScanResult $result) {}

            public function scan(mixed $stream): ScanResult
            {
                $this->calls++;

                return $this->result;
            }
        };

        $this->app->instance(FileScanner::class, $scanner);

        return $scanner;
    }

    /**
     * @return array{0: User, 1: DdEngagement}
     */
    private function ddEngagement(string $advisorEmail = 'data-room-dd-advisor@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::DUE_DILIGENCE,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Buyer Holdings Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::DUE_DILIGENCE->value],
        ]);

        $conflict = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::DUE_DILIGENCE,
            existingRelationship: false,
        );

        $engagement = app(DdOnboarding::class)->start(
            buyer: $client,
            advisor: $advisor,
            conflict: $conflict,
            targetName: 'Target Supplies Limited',
            targetDetails: ['industry' => 'Distribution'],
        );

        return [$advisor, $engagement];
    }
}
