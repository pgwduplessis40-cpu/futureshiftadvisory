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
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class VerificationOutcomesTest extends TestCase
{
    use RefreshDatabase;

    private string $secureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secureRoot = storage_path('framework/testing/document-verification');
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

    public function test_fake_ai_discrepancy_creates_urgent_notification_and_advisor_panel_flag(): void
    {
        [$clientUser, $client] = $this->clientUserWithClient();
        $advisor = $this->advisorFor($client);
        $question = $this->questionnaireQuestion();

        $response = $this->actingAsMfa($clientUser)
            ->withHeader('Accept', 'application/json')
            ->post(route('portal.documents.store'), [
                'file' => UploadedFile::fake()->createWithContent('cash-position.pdf', "%PDF-1.4\nCash balance is 100."),
                'category' => Document::CATEGORY_FINANCIAL_STATEMENT,
                'question_id' => $question->id,
                'question_prompt' => $question->prompt,
                'claim_value' => 'Accuracy discrepancy: cash balance is 500.',
            ]);

        $response->assertCreated();

        $documentId = $response->json('document.id');
        $this->assertDatabaseHas('document_verifications', [
            'document_id' => $documentId,
            'outcome' => DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $advisor->getKey(),
            'type' => 'document.verification.discrepancy',
            'urgency' => 'urgent',
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->has('documentVerificationFlags', 1)
                ->where(
                    'documentVerificationFlags.0.outcome',
                    DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY,
                )
                ->where('documentVerificationFlags.0.document_name', 'cash-position.pdf'));
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
            'nzbn' => '9429000000001',
            'legal_name' => 'Discrepancy Test Limited',
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

    private function advisorFor(Client $client): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $advisor;
    }

    private function questionnaireQuestion(): QuestionnaireQuestion
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => 'wo18-discrepancy',
            'title' => 'WO-18 Discrepancy Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Finance',
        ]);

        return $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::TEXT,
            'prompt' => 'What cash position should this document support?',
            'required' => true,
        ]);
    }
}
