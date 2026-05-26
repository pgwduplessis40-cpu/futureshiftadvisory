<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Enums\NpoTiritiMode;
use App\Models\NpoEngagement;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\QuestionnaireSection;
use App\Models\User;
use App\Services\Questionnaires\QuestionnaireRuleEngine;
use Illuminate\Support\Str;

final class NpoQuestionnaireScoring
{
    /**
     * @var array<int, int>
     */
    private const DIMENSION_SECTION_ORDERS = [
        NpoHealthScorer::DIMENSION_MISSION_STRATEGY => 3,
        NpoHealthScorer::DIMENSION_SERVICE_OPERATIONS => 4,
        NpoHealthScorer::DIMENSION_GOVERNANCE_COMPLIANCE => 5,
        NpoHealthScorer::DIMENSION_FINANCIAL_SUSTAINABILITY => 6,
        NpoHealthScorer::DIMENSION_PEOPLE_CAPABILITY => 7,
        NpoHealthScorer::DIMENSION_IMPACT_MEASUREMENT => 8,
        NpoHealthScorer::DIMENSION_FUNDING_RESILIENCE => 10,
        NpoHealthScorer::DIMENSION_TE_TIRITI => 9,
    ];

    public function __construct(
        private readonly NpoHealthScorer $scorer,
        private readonly QuestionnaireRuleEngine $rules,
    ) {}

    public function record(NpoEngagement $engagement, QuestionnaireResponse $response, ?User $actor = null): void
    {
        $response->loadMissing('questionnaire.sections.questions', 'answers.question.section');

        $answers = $response->answers->mapWithKeys(fn (QuestionnaireAnswer $answer): array => [
            $answer->question_id => [
                'value' => $answer->value,
                'attached_document_ids' => $answer->attached_document_ids ?? [],
            ],
        ])->all();
        $visibleQuestionIds = array_fill_keys(
            $this->rules->visibleQuestionIds($response->questionnaire, $answers),
            true,
        );
        $answersByQuestion = $response->answers->keyBy('question_id');
        $sectionsByOrder = $response->questionnaire->sections->keyBy('order');
        $scores = [];
        $findings = [];

        foreach ($this->scorer->dimensionsForMode($engagement->tiriti_mode ?? NpoTiritiMode::Woven) as $dimension) {
            $dimensionNumber = (int) $dimension['number'];
            $section = $sectionsByOrder->get(self::DIMENSION_SECTION_ORDERS[$dimensionNumber] ?? 0);
            $sectionQuestions = $section instanceof QuestionnaireSection
                ? $section->questions->filter(fn (QuestionnaireQuestion $question): bool => isset($visibleQuestionIds[$question->id]))
                : collect();
            $total = $sectionQuestions->count();
            $answered = $sectionQuestions
                ->filter(fn (QuestionnaireQuestion $question): bool => $this->isAnswered($answersByQuestion->get($question->id)))
                ->count();
            $score = $total === 0 ? 0 : (int) round(($answered / $total) * 100);
            $sectionTitle = $section instanceof QuestionnaireSection ? $section->title : 'Unmapped NPO section';

            $scores[$dimension['key']] = $score;
            $findings[$dimension['key']] = [[
                'id' => (string) Str::uuid(),
                'severity' => $score >= 80 ? 'info' : ($score >= 50 ? 'medium' : 'high'),
                'title' => "Questionnaire completion for {$dimension['label']}",
                'body' => "{$answered} of {$total} visible questions were completed in {$sectionTitle}.",
                'source' => 'standard_npo_questionnaire',
                'attributions' => [[
                    'source_reference' => 'questionnaire_response:'.$response->getKey(),
                    'claim' => "{$sectionTitle} completion {$answered}/{$total}",
                ]],
            ]];
        }

        $this->scorer->recordAssessment($engagement, $scores, $findings, $actor);
    }

    private function isAnswered(?QuestionnaireAnswer $answer): bool
    {
        if (! $answer instanceof QuestionnaireAnswer) {
            return false;
        }

        $documentIds = $answer->attached_document_ids ?? [];
        if (is_array($documentIds) && $documentIds !== []) {
            return true;
        }

        $value = $answer->value;
        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && trim((string) $value) !== '';
    }
}
