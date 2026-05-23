<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\EntrepreneurProfile;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Documents\DocumentVerificationBlockedException;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Entrepreneurs\PlanBuilder;
use App\Services\Entrepreneurs\PlanDocuments;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PlanDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->app->bind(AiClient::class, FakeAiClient::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');
    }

    public function test_plan_section_attachment_is_verified_and_persisted(): void
    {
        [$advisor, $profile, $section] = $this->section();
        $document = $this->document($profile, 'pilot.txt', 'Three paid pilots support the demand claim.');

        $verification = app(PlanDocuments::class)->attachAndVerify(
            section: $section,
            document: $document,
            actor: $advisor,
            claim: 'Three paid pilots support the demand claim.',
        );

        $this->assertSame(DocumentVerification::OUTCOME_VERIFIED, $verification->outcome);
        $this->assertSame($profile->id, $verification->entrepreneur_profile_id);
        $this->assertSame($section->id, $verification->plan_section_id);
        $this->assertContains($document->id, $section->refresh()->attached_document_ids);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.plan_document_verified',
            'subject_id' => $verification->id,
        ]);
    }

    public function test_verified_document_support_raises_criterion_score(): void
    {
        [$advisor, $profile, $section] = $this->section('score-doc-founder@example.test');
        $document = $this->document($profile, 'interviews.txt', 'Interview notes support the customer segment claim.');
        $documents = app(PlanDocuments::class);

        $this->assertSame(52, $documents->criterionScoreWithDocumentSupport($section, 52));

        $documents->attachAndVerify(
            section: $section,
            document: $document,
            actor: $advisor,
            claim: 'Interview notes support the customer segment claim.',
        );

        $this->assertGreaterThan(52, $documents->criterionScoreWithDocumentSupport($section->refresh(), 52));
    }

    public function test_accuracy_discrepancy_blocks_scoring_until_resolved(): void
    {
        [$advisor, $profile, $section] = $this->section('discrepancy-doc-founder@example.test');
        $document = $this->document($profile, 'conflict.txt', 'This document does not match the claimed demand evidence.');
        $documents = app(PlanDocuments::class);

        $verification = $documents->attachAndVerify(
            section: $section,
            document: $document,
            actor: $advisor,
            claim: 'The plan has confirmed demand evidence.',
        );

        $this->assertSame(DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY, $verification->outcome);

        $this->expectException(DocumentVerificationBlockedException::class);
        $documents->ensureScoringClear($section->refresh());
    }

    /**
     * @return array{0: User, 1: EntrepreneurProfile, 2: PlanSection}
     */
    private function section(string $email = 'document-founder@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Document Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'concept_summary' => 'Document-backed plan concept.',
        ]);
        $validation = app(IdeaValidationService::class)->evaluate($profile, [
            'problem' => 'Operators need evidence-backed planning before launch.',
            'target_customer' => 'Regional owner operators.',
            'solution' => 'A verified planning workflow.',
            'value_proposition' => 'Less launch risk through verified claims.',
            'demand_signal' => 'Customer interviews are complete.',
            'revenue_model' => 'Advisory subscription and implementation support.',
        ], $advisor);
        app(IdeaValidationService::class)->passAdvisorGate($validation, $advisor, 'Ready for verified planning.');
        $plan = app(PlanBuilder::class)->start($profile, $advisor);
        $section = app(PlanBuilder::class)->upsertSection(
            plan: $plan,
            phaseKey: 'market',
            key: 'verified-market',
            title: 'Verified market evidence',
            body: 'The founder has identified a specific customer segment and supporting evidence.',
            actor: $advisor,
        );

        return [$advisor, $profile, $section];
    }

    private function document(EntrepreneurProfile $profile, string $filename, string $content): Document
    {
        $path = 'plan-attachments/'.Str::uuid().'-'.$filename;
        Storage::disk('secure_local')->put($path, $content);

        return Document::query()->create([
            'client_id' => null,
            'entrepreneur_profile_id' => $profile->id,
            'category' => Document::CATEGORY_PLAN_ATTACHMENT,
            'original_filename' => $filename,
            'stored_path' => $path,
            'byte_size' => strlen($content),
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', $content),
            'uploaded_by_user_id' => $profile->user_id,
            'scanner_result' => Document::SCANNER_CLEAN,
            'scanner_payload' => ['engine' => 'fixture'],
        ]);
    }
}
