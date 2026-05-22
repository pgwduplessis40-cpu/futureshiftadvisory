<?php

declare(strict_types=1);

namespace Tests\Feature\Questionnaire;

use App\Console\Commands\RunQuestionnaireOptimisationLayer;
use App\Enums\EngagementType;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\LearningUpdate;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Services\Questionnaires\QuestionnaireOptimisationLayer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuestionnaireOptimisationLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_questionnaire_optimisation_emits_governed_candidate_without_auto_apply(): void
    {
        $question = $this->questionnaireQuestion();

        $this->response($question, 'Clear answer');
        $this->response($question, '');
        $this->response($question, null);

        $this->artisan(RunQuestionnaireOptimisationLayer::class, [
            '--minimum-responses' => 3,
            '--blank-rate-threshold' => 0.5,
            '--window-end' => now()->toIso8601String(),
        ])->assertSuccessful();

        $candidate = LearningUpdate::query()
            ->where('layer_id', QuestionnaireOptimisationLayer::LAYER_ID)
            ->firstOrFail();

        $this->assertSame(LearningUpdate::STATUS_DETECTED, $candidate->status);
        $this->assertSame('questionnaire_optimisation_layer', $candidate->source['type']);
        $this->assertFalse($candidate->proposed_change['automatic_application']);
        $this->assertSame(3, $candidate->clients_affected);
        $this->assertSame(0.6667, $candidate->evidence['blank_rate']);
        $this->assertDatabaseHas('learning_layer_runs', [
            'layer_id' => QuestionnaireOptimisationLayer::LAYER_ID,
            'status' => 'completed',
            'candidates_created' => 1,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'questionnaire_optimisation_layer.ran',
        ]);
        $this->assertDatabaseCount('learning_update_implementations', 0);

        $this->artisan(RunQuestionnaireOptimisationLayer::class, [
            '--minimum-responses' => 3,
            '--blank-rate-threshold' => 0.5,
            '--window-end' => now()->toIso8601String(),
        ])->assertSuccessful();

        $this->assertDatabaseCount('learning_updates', 1);
    }

    private function questionnaireQuestion(): QuestionnaireQuestion
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => 'wo63',
            'title' => 'Standard Advisory Optimisation Fixture',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Operations',
            'help_text' => null,
        ]);

        return $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::LONG_TEXT,
            'prompt' => 'Describe the operational constraint that is hardest to quantify.',
            'required' => true,
        ]);
    }

    private function response(QuestionnaireQuestion $question, ?string $value): QuestionnaireResponse
    {
        $questionnaire = $question->section->questionnaire;
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => 'Questionnaire Optimisation '.fake()->unique()->company(),
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);

        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->getKey(),
            'questionnaire_id' => $questionnaire->getKey(),
            'submitted_at' => now(),
        ]);

        $response->answers()->create([
            'question_id' => $question->getKey(),
            'value' => ['value' => $value],
        ]);

        return $response;
    }
}
