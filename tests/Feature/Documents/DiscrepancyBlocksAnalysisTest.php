<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\User;
use App\Services\Documents\DocumentVerificationBlockedException;
use App\Services\Documents\DocumentVerificationGate;
use App\Services\Storage\SecureFileWriter;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DiscrepancyBlocksAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private string $secureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secureRoot = storage_path('framework/testing/document-gate');
        File::deleteDirectory($this->secureRoot);
        Config::set('filesystems.disks.secure_local.root', $this->secureRoot);
        Storage::forgetDisk('secure_local');

        app(RequestContext::class)->apply('system', []);
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('secure_local');
        File::deleteDirectory($this->secureRoot);

        parent::tearDown();
    }

    public function test_document_verification_gate_blocks_phase_two_placeholder_analysis(): void
    {
        [$client, $document] = $this->clientWithDocument();

        DocumentVerification::query()->create([
            'document_id' => $document->id,
            'client_id' => $client->id,
            'claim_source' => 'test',
            'context_hash' => hash('sha256', 'blocking-claim'),
            'claim_text' => 'Accuracy discrepancy: inventory count is 500.',
            'outcome' => DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY,
            'client_explanation' => 'Related analysis is paused.',
            'verified_at' => now(),
        ]);

        $analysis = new class(app(DocumentVerificationGate::class))
        {
            public function __construct(private readonly DocumentVerificationGate $gate) {}

            public function render(Client $client): string
            {
                $this->gate->ensureClear($client);

                return 'phase-two-analysis';
            }
        };

        $this->expectException(DocumentVerificationBlockedException::class);

        $analysis->render($client);
    }

    /**
     * @return array{0: Client, 1: Document}
     */
    private function clientWithDocument(): array
    {
        $user = User::factory()->create();

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000002',
            'legal_name' => 'Gate Test Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
        ]);

        $document = app(SecureFileWriter::class)->write(
            uploadedFile: UploadedFile::fake()->createWithContent('inventory.txt', 'Inventory count is 100.'),
            owner: $user,
            category: Document::CATEGORY_OTHER,
            clientId: (string) $client->getKey(),
        );

        return [$client, $document];
    }
}
