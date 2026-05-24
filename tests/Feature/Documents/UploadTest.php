<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Enums\EngagementType;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\User;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\ScanResult;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class UploadTest extends TestCase
{
    use RefreshDatabase;

    private string $secureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secureRoot = storage_path('framework/testing/document-upload');
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

    public function test_uploading_clean_file_scans_persists_and_verifies_attached_claim(): void
    {
        $scanner = new class implements FileScanner
        {
            public int $calls = 0;

            public function scan(mixed $stream): ScanResult
            {
                $this->calls++;

                return ScanResult::clean(['engine' => 'test-scanner']);
            }
        };
        $this->app->instance(FileScanner::class, $scanner);

        [$user] = $this->clientUserWithClient();
        $question = $this->questionnaireQuestion();

        $response = $this->actingAsMfa($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('portal.documents.store'), [
                'file' => UploadedFile::fake()->createWithContent('cashflow.txt', 'Projected revenue is 120000.'),
                'category' => Document::CATEGORY_FINANCIAL_STATEMENT,
                'question_id' => $question->id,
                'question_prompt' => $question->prompt,
                'claim_value' => 'Projected revenue is 120000.',
            ]);

        $response->assertCreated();

        $documentId = $response->json('document.id');
        $this->assertSame(1, $scanner->calls);
        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
        $this->assertDatabaseHas('document_verifications', [
            'document_id' => $documentId,
            'questionnaire_question_id' => $question->id,
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
        ]);
        $this->assertSame(
            DocumentVerification::OUTCOME_VERIFIED,
            $response->json('document.verifications.0.outcome'),
        );
    }

    public function test_uploaded_file_persistence_remains_inside_secure_file_writer(): void
    {
        $root = base_path('app');
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $contents = file_get_contents($path) ?: '';

            if (! str_contains($contents, "Storage::disk('secure_local')->put")) {
                continue;
            }

            if (
                str_ends_with($path, 'app/Services/Storage/SecureFileWriter.php')
                || str_ends_with($path, 'app/Services/Panels/PanelOnboarding.php')
                || str_ends_with($path, 'app/Services/Payments/ReceiptGenerator.php')
                || str_ends_with($path, 'app/Services/Proposals/ProposalBuilder.php')
                || str_ends_with($path, 'app/Services/Proposals/SignoffFlow.php')
                || str_ends_with($path, 'app/Services/Reports/ReportComposer.php')
                || str_ends_with($path, 'app/Services/Terms/SignedAcceptancePdf.php')
            ) {
                continue;
            }

            $violations[] = $path;
        }

        $this->assertSame([], $violations);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUserWithClient(): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000000',
            'legal_name' => 'Upload Test Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$user, $client];
    }

    private function questionnaireQuestion(): QuestionnaireQuestion
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => 'wo18',
            'title' => 'WO-18 Upload Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Finance',
        ]);

        return $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::TEXT,
            'prompt' => 'What revenue claim should this document support?',
            'required' => true,
        ]);
    }
}
