<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\NpoTiritiMode;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Jobs\VerifyDocumentJob;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\NpoDimensionScore;
use App\Models\NpoEngagement;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\ScanResult;
use App\Services\Portal\OnboardingWizard;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StandardNpoQuestionnaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class StandardNpoQuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    private string $secureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secureRoot = storage_path('framework/testing/standard-npo-questionnaire');
        File::deleteDirectory($this->secureRoot);
        Config::set('filesystems.disks.secure_local.root', $this->secureRoot);
        Storage::forgetDisk('secure_local');

        $this->seed([RoleSeeder::class, StandardNpoQuestionnaireSeeder::class]);
        app(RequestContext::class)->apply('system', []);
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('secure_local');
        File::deleteDirectory($this->secureRoot);

        parent::tearDown();
    }

    public function test_standard_npo_set_is_seeded_admin_managed_and_versioned(): void
    {
        $questionnaire = $this->standardNpoQuestionnaire();
        $uploads = $questionnaire->sections
            ->flatMap->questions
            ->filter(fn (QuestionnaireQuestion $question): bool => $question->type === QuestionnaireQuestionType::FILE_ATTACH)
            ->values();

        $this->assertSame('Standard NPO Health Questionnaire', $questionnaire->title);
        $this->assertSame([
            'Organisation Profile',
            'Strategic Direction',
            'Mission and Strategy',
            'Service Delivery and Operations',
            'Governance and Compliance',
            'Financial Sustainability',
            'People and Capability',
            'Impact Measurement',
            'Te Tiriti',
            'Funding Resilience',
        ], $questionnaire->sections->pluck('title')->all());
        $this->assertCount(8, $uploads);
        $this->assertCount(3, $uploads->where('required', true));

        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.questionnaires.store'), ['set' => QuestionnaireSet::STANDARD_NPO->value])
            ->assertRedirect();

        $draft = Questionnaire::query()
            ->where('set', QuestionnaireSet::STANDARD_NPO->value)
            ->where('version', '2')
            ->whereNull('published_at')
            ->with('sections.questions')
            ->firstOrFail();

        $this->assertCount(10, $draft->sections);
        $this->assertSame('Standard NPO Health Questionnaire', $draft->title);
    }

    public function test_te_tiriti_section_visibility_tracks_tiriti_mode(): void
    {
        [$user, , $engagement] = $this->npoClientUser(mode: NpoTiritiMode::Woven);

        $this->actingAsMfa($user)
            ->get(route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/onboarding/Step')
                ->where('questionnaire.set', QuestionnaireSet::STANDARD_NPO->value)
                ->has('questionnaire.schema.sections', 9)
                ->where('questionnaire.schema.sections.8.title', 'Funding Resilience'));

        $engagement->forceFill(['tiriti_mode' => NpoTiritiMode::Standalone])->save();

        $this->actingAsMfa($user)
            ->get(route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/onboarding/Step')
                ->has('questionnaire.schema.sections', 10)
                ->where('questionnaire.schema.sections.8.title', 'Te Tiriti')
                ->where('questionnaire.schema.sections.9.title', 'Funding Resilience'));
    }

    public function test_completion_stamps_full_npo_response_and_feeds_health_dimensions(): void
    {
        Queue::fake();

        [$user, $client, $engagement] = $this->npoClientUser(mode: NpoTiritiMode::Standalone);
        $questionnaire = $this->standardNpoQuestionnaire();

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]), [
                'answers' => $this->answersFor($questionnaire),
            ])
            ->assertRedirect(route('portal.onboarding.step', [
                'step' => OnboardingWizard::STEP_DOCUMENTS,
            ], absolute: false));

        $response = $questionnaire->responses()->firstOrFail();
        $state = $client->refresh()->onboarding_wizard_state;

        $this->assertSame($client->id, $response->client_id);
        $this->assertSame($engagement->id, $response->npo_engagement_id);
        $this->assertSame($engagement->id, $state['steps'][OnboardingWizard::STEP_QUESTIONNAIRE]['npo_engagement_id']);
        $this->assertSame(8, NpoDimensionScore::query()->where('npo_engagement_id', $engagement->id)->count());
        $this->assertDatabaseHas('npo_dimension_scores', [
            'npo_engagement_id' => $engagement->id,
            'dimension_number' => 8,
            'score' => 100,
            'health_score' => 100,
        ]);
        Queue::assertPushed(VerifyDocumentJob::class);
    }

    public function test_standard_npo_upload_questions_are_scanned_and_verified(): void
    {
        $scanner = new class implements FileScanner
        {
            public int $calls = 0;

            public function scan(mixed $stream): ScanResult
            {
                $this->calls++;

                return ScanResult::clean(['engine' => 'standard-npo-test-scanner']);
            }
        };
        $this->app->instance(FileScanner::class, $scanner);

        [$user, $client] = $this->npoClientUser();
        $question = $this->standardNpoQuestionnaire()
            ->sections
            ->flatMap->questions
            ->first(fn (QuestionnaireQuestion $question): bool => $question->type === QuestionnaireQuestionType::FILE_ATTACH);

        $this->assertInstanceOf(QuestionnaireQuestion::class, $question);

        $response = $this->actingAsMfa($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('portal.documents.store'), [
                'file' => UploadedFile::fake()->createWithContent('constitution.txt', 'The trust deed confirms charitable purpose.'),
                'category' => Document::CATEGORY_COMPLIANCE_DOC,
                'question_id' => $question->id,
                'question_prompt' => $question->prompt,
                'claim_value' => 'The trust deed confirms charitable purpose.',
            ]);

        $response->assertCreated();

        $documentId = $response->json('document.id');
        $this->assertSame(1, $scanner->calls);
        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'client_id' => $client->id,
            'category' => Document::CATEGORY_COMPLIANCE_DOC,
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
        $this->assertDatabaseHas('document_verifications', [
            'document_id' => $documentId,
            'questionnaire_question_id' => $question->id,
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
        ]);
    }

    private function standardNpoQuestionnaire(): Questionnaire
    {
        return Questionnaire::query()
            ->forSet(QuestionnaireSet::STANDARD_NPO)
            ->published()
            ->with('sections.questions')
            ->firstOrFail();
    }

    /**
     * @return array{0: User, 1: Client, 2: NpoEngagement}
     */
    private function npoClientUser(NpoTiritiMode $mode = NpoTiritiMode::Standalone): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'NPO Client Owner',
            'email' => fake()->unique()->safeEmail(),
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Community Health Trust',
            'entity_type' => 'Charitable Trust Board',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
            'onboarding_wizard_state' => [
                'current_step' => 5,
                'completed_steps' => [
                    OnboardingWizard::STEP_WELCOME,
                    OnboardingWizard::STEP_IDENTITY,
                    OnboardingWizard::STEP_BUSINESS_SNAPSHOT,
                    OnboardingWizard::STEP_GOALS,
                ],
                'steps' => [],
            ],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::NPO->value],
        ]);

        $engagement = NpoEngagement::query()->create([
            'client_id' => $client->getKey(),
            'sub_type' => NpoEngagementSubType::StandardNpo,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
            'tiriti_mode' => $mode,
        ]);

        return [$user, $client, $engagement];
    }

    /**
     * @return array<string, array{value:mixed, attached_document_ids:array<int, string>}>
     */
    private function answersFor(Questionnaire $questionnaire): array
    {
        $answers = [];

        foreach ($questionnaire->sections as $section) {
            foreach ($section->questions as $question) {
                $answers[$question->id] = match ($question->type) {
                    QuestionnaireQuestionType::TEXT,
                    QuestionnaireQuestionType::LONG_TEXT => [
                        'value' => 'Standard NPO answer for '.$section->title,
                        'attached_document_ids' => [],
                    ],
                    QuestionnaireQuestionType::NUMBER,
                    QuestionnaireQuestionType::CURRENCY => [
                        'value' => 6,
                        'attached_document_ids' => [],
                    ],
                    QuestionnaireQuestionType::DATE => [
                        'value' => now()->toDateString(),
                        'attached_document_ids' => [],
                    ],
                    QuestionnaireQuestionType::SINGLE_SELECT,
                    QuestionnaireQuestionType::LIKERT => [
                        'value' => (string) ($question->options[0]['value'] ?? ''),
                        'attached_document_ids' => [],
                    ],
                    QuestionnaireQuestionType::MULTI_SELECT => [
                        'value' => [(string) ($question->options[0]['value'] ?? '')],
                        'attached_document_ids' => [],
                    ],
                    QuestionnaireQuestionType::FILE_ATTACH => [
                        'value' => null,
                        'attached_document_ids' => [(string) Str::uuid()],
                    ],
                };
            }
        }

        return $answers;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        TermsVersion::query()->delete();

        return $user;
    }
}
