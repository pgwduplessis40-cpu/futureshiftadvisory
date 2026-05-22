<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFeedback;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\LearningUpdate;
use App\Models\User;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Analysis\FeedbackLearningLayer;
use App\Services\Analysis\FeedbackRecorder;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AnalysisFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_advisor_feedback_persists_and_is_audited(): void
    {
        [$advisor, $client, $finding] = $this->findingWithAdvisor();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.analysis-findings.feedback.store', $finding), [
                'decision' => AnalysisFeedback::DECISION_CORRECT,
                'corrected_body' => 'Margin pressure should be framed as a watch item, not a confirmed decline.',
                'note' => 'Use softer language until source documents are refreshed.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('analysis_feedback', [
            'analysis_finding_id' => $finding->id,
            'advisor_user_id' => $advisor->id,
            'decision' => AnalysisFeedback::DECISION_CORRECT,
            'corrected_body' => 'Margin pressure should be framed as a watch item, not a confirmed decline.',
        ]);

        $feedback = AnalysisFeedback::query()->firstOrFail();
        $this->assertDatabaseHas('audit_events', [
            'action' => 'analysis_feedback.recorded',
            'subject_type' => AnalysisFeedback::class,
            'subject_id' => $feedback->id,
            'client_id' => $client->id,
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->has('client.analysis_findings', 1)
                ->where('client.analysis_findings.0.id', $finding->id)
                ->where('client.analysis_findings.0.feedback_count', 1)
                ->where('client.analysis_findings.0.latest_feedback.0.decision', AnalysisFeedback::DECISION_CORRECT)
                ->where('client.analysis_findings.0.feedback_store_url', route('advisor.analysis-findings.feedback.store', $finding, absolute: false)));
    }

    public function test_feedback_learning_layer_creates_one_detected_candidate_at_threshold(): void
    {
        [$advisor,, $finding] = $this->findingWithAdvisor();
        $recorder = app(FeedbackRecorder::class);

        foreach (range(1, FeedbackLearningLayer::DEFAULT_THRESHOLD) as $index) {
            $recorder->record(
                finding: $index === 1 ? $finding : $this->findingForModule(AnalysisModule::Financial),
                advisor: $advisor,
                decision: AnalysisFeedback::DECISION_CORRECT,
                correctedBody: "Correction sample {$index}",
            );
        }

        $this->assertDatabaseCount('analysis_feedback', FeedbackLearningLayer::DEFAULT_THRESHOLD);

        $this->artisan('analysis:feedback-learning', [
            '--threshold' => FeedbackLearningLayer::DEFAULT_THRESHOLD,
            '--window-days' => 30,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('learning_updates', [
            'layer_id' => FeedbackLearningLayer::LAYER_ID,
            'status' => LearningUpdate::STATUS_DETECTED,
            'clients_affected' => 3,
        ]);
        $this->assertDatabaseHas('learning_layer_runs', [
            'layer_id' => FeedbackLearningLayer::LAYER_ID,
            'status' => 'completed',
            'candidates_created' => 1,
        ]);
        $this->assertDatabaseCount('learning_update_implementations', 0);

        $this->artisan('analysis:feedback-learning', [
            '--threshold' => FeedbackLearningLayer::DEFAULT_THRESHOLD,
            '--window-days' => 30,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('learning_updates', 1);
        $this->assertDatabaseHas('learning_layer_runs', [
            'layer_id' => FeedbackLearningLayer::LAYER_ID,
            'status' => 'completed',
            'candidates_created' => 0,
        ]);
    }

    /**
     * @return array{0: User, 1: Client, 2: AnalysisFinding}
     */
    private function findingWithAdvisor(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $finding = $this->findingForModule(AnalysisModule::Financial, $advisor);

        return [$advisor, $finding->client, $finding];
    }

    private function findingForModule(AnalysisModule $module, ?User $advisor = null): AnalysisFinding
    {
        app(RequestContext::class)->apply('system', [], $advisor?->getKey() === null ? null : (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Analysis Feedback '.fake()->unique()->company(),
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor?->getKey(),
        ]);

        if ($advisor instanceof User) {
            ClientTeamMember::query()->create([
                'client_id' => $client->id,
                'user_id' => $advisor->getKey(),
                'role' => 'lead_advisor',
                'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
            ]);
        }

        $run = AnalysisRun::query()->create([
            'client_id' => $client->id,
            'module' => $module,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [AnalysisLens::Diagnostic->value],
            'data_quality_snapshot' => ['level' => Client::DATA_QUALITY_LOW],
            'ai_model' => 'feedback-test',
            'prompt_version' => 'wo32-feedback-test',
            'prompt_hash' => hash('sha256', $client->id.$module->value),
            'started_at' => now(),
            'completed_at' => now(),
            'created_by_user_id' => $advisor?->getKey(),
        ]);

        return AnalysisFinding::query()->create([
            'analysis_run_id' => $run->id,
            'client_id' => $client->id,
            'lens' => AnalysisLens::Diagnostic,
            'severity' => FindingSeverity::Medium,
            'title' => 'Margin pressure',
            'body' => 'Gross margin appears to be compressing.',
            'attributions' => [
                ['claim' => 'Gross margin pressure', 'source_reference' => 'questionnaire:feedback-test'],
            ],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_NONE,
            'uncertainty' => Uncertainty::Medium,
            'bias_signals' => [],
        ]);
    }
}
