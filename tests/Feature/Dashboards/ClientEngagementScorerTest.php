<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboards;

use App\Enums\EngagementType;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\Goal;
use App\Models\MessageThread;
use App\Models\Milestone;
use App\Models\Questionnaire;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\QuestionnaireSection;
use App\Services\Dashboards\ClientEngagementScorer;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\DataQuality\DataQualitySignal;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientEngagementScorerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_engagement_score_components_display_fields_and_weakest_component_are_deterministic(): void
    {
        $client = $this->client();
        $questions = $this->questionnaire($client, 2);
        $this->answer($questions[0], 'Revenue is stabilising.');
        $this->verifiedDocument($client, 'verified-a.pdf');
        $this->verifiedDocument($client, 'verified-b.pdf');
        $this->milestones($client);
        MessageThread::query()->create([
            'client_id' => $client->id,
            'subject' => 'Quarterly check-in',
            'last_activity_at' => now()->subDays(15),
        ]);

        $score = app(ClientEngagementScorer::class)->score($client);
        $dataQualityScore = app(DataQualityScorer::class)->score($client);
        $documentSignal = collect($dataQualityScore->signals)
            ->first(fn (DataQualitySignal $signal): bool => $signal->key === 'verified_documents');

        $this->assertSame('amber', $score['level']);
        $this->assertSame(65, $score['score']);
        $this->assertSame([
            'questionnaire_pct' => 50,
            'documents_pct' => 100,
            'milestones_on_track_pct' => 50,
            'comms_recency_pct' => 50,
        ], $score['scores']);
        $this->assertSame($documentSignal?->score, $score['scores']['documents_pct']);
        $this->assertSame([
            'overdue_count' => 1,
            'blocked_count' => 1,
            'last_comms_days' => 15,
        ], $score['display']);
        $this->assertSame('questionnaire_pct', $score['weakest_component']);
        $this->assertSame('questionnaire', $score['focus_section']);
    }

    public function test_composite_score_rounds_half_up_before_banding(): void
    {
        config()->set('dashboards.engagement.weights', [
            'questionnaire_pct' => 0.51,
            'documents_pct' => 0.0,
            'milestones_on_track_pct' => 0.49,
            'comms_recency_pct' => 0.0,
        ]);

        $client = $this->client();
        $questions = $this->questionnaire($client, 2);
        $this->answer($questions[0], 'Answered.');

        $score = app(ClientEngagementScorer::class)->score($client);

        $this->assertSame(75, $score['score']);
        $this->assertSame('green', $score['level']);
        $this->assertSame(100, $score['scores']['milestones_on_track_pct']);
    }

    public function test_empty_questionnaire_unverified_documents_and_never_messaged_are_graceful(): void
    {
        $client = $this->client();
        $this->unverifiedDocument($client, 'pending-verification.pdf');

        $score = app(ClientEngagementScorer::class)->score($client);

        $this->assertSame('red', $score['level']);
        $this->assertSame(25, $score['score']);
        $this->assertSame([
            'questionnaire_pct' => 0,
            'documents_pct' => 0,
            'milestones_on_track_pct' => 100,
            'comms_recency_pct' => 0,
        ], $score['scores']);
        $this->assertSame([
            'overdue_count' => 0,
            'blocked_count' => 0,
            'last_comms_days' => null,
        ], $score['display']);
        $this->assertSame('questionnaire_pct', $score['weakest_component']);
        $this->assertSame('questionnaire', $score['focus_section']);
    }

    /**
     * @return array<int, QuestionnaireQuestion>
     */
    private function questionnaire(Client $client, int $questionCount): array
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => 'engagement-'.str()->random(12),
            'title' => 'Engagement questionnaire',
            'published_at' => now(),
        ]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id,
            'order' => 1,
            'title' => 'Basics',
        ]);
        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
        ]);

        $questions = [];

        foreach (range(1, $questionCount) as $order) {
            $questions[] = QuestionnaireQuestion::query()->create([
                'questionnaire_section_id' => $section->id,
                'order' => $order,
                'type' => QuestionnaireQuestionType::TEXT,
                'prompt' => "Question {$order}",
                'required' => true,
            ]);
        }

        $response->setRelation('questionnaire', $questionnaire);

        return $questions;
    }

    private function answer(QuestionnaireQuestion $question, string $value): void
    {
        $response = QuestionnaireResponse::query()->firstOrFail();

        QuestionnaireAnswer::query()->create([
            'response_id' => $response->id,
            'question_id' => $question->id,
            'value' => ['text' => $value],
        ]);
    }

    private function verifiedDocument(Client $client, string $filename): void
    {
        $document = Document::query()->create([
            'client_id' => $client->id,
            'category' => Document::CATEGORY_FINANCIAL_STATEMENT,
            'original_filename' => $filename,
            'stored_path' => 'secure/'.$filename,
            'byte_size' => 100,
            'sha256' => hash('sha256', $filename),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);

        DocumentVerification::query()->create([
            'document_id' => $document->id,
            'client_id' => $client->id,
            'claim_source' => 'engagement_test',
            'context_hash' => hash('sha256', $filename.'-claim'),
            'claim_text' => 'Verified claim.',
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
            'confidence' => 0.99,
            'verified_at' => now(),
        ]);
    }

    private function unverifiedDocument(Client $client, string $filename): void
    {
        Document::query()->create([
            'client_id' => $client->id,
            'category' => Document::CATEGORY_FINANCIAL_STATEMENT,
            'original_filename' => $filename,
            'stored_path' => 'secure/'.$filename,
            'byte_size' => 100,
            'sha256' => hash('sha256', $filename),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
    }

    private function milestones(Client $client): void
    {
        $goal = Goal::query()->create([
            'client_id' => $client->id,
            'title' => 'Improve reporting',
            'status' => Goal::STATUS_ACTIVE,
        ]);

        foreach ([
            ['Overdue', Milestone::STATUS_PENDING, now()->subDay()],
            ['Blocked', Milestone::STATUS_BLOCKED, now()->addMonth()],
            ['On track one', Milestone::STATUS_IN_PROGRESS, now()->addMonth()],
            ['On track two', Milestone::STATUS_PENDING, now()->addMonth()],
        ] as [$title, $status, $dueDate]) {
            Milestone::query()->create([
                'goal_id' => $goal->id,
                'client_id' => $client->id,
                'title' => $title,
                'status' => $status,
                'due_date' => $dueDate,
            ]);
        }
    }

    private function client(): Client
    {
        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000600',
            'legal_name' => 'Engagement Test Limited',
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
        ]);
    }
}
