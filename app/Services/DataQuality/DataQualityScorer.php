<?php

declare(strict_types=1);

namespace App\Services\DataQuality;

use App\Enums\QuestionnaireQuestionType;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Services\Questionnaires\QuestionnaireRuleEngine;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final class DataQualityScorer
{
    private const WEIGHT_QUESTIONNAIRE_COMPLETENESS = 35;

    private const WEIGHT_ANSWER_DOCUMENT_SUPPORT = 25;

    private const WEIGHT_VERIFIED_DOCUMENTS = 25;

    private const WEIGHT_FRESHNESS = 15;

    public function __construct(private readonly QuestionnaireRuleEngine $rules) {}

    public function score(Client $client): DataQualityScore
    {
        $responses = QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->with(['answers.question', 'questionnaire.sections.questions'])
            ->get();

        $documents = Document::query()
            ->visibleToClients()
            ->where('client_id', $client->getKey())
            ->with('verifications')
            ->get();

        $verifications = DocumentVerification::query()
            ->where('client_id', $client->getKey())
            ->get();

        $signals = [
            $this->questionnaireCompletenessSignal($responses),
            $this->answerDocumentSupportSignal($responses),
            $this->verifiedDocumentsSignal($documents, $verifications),
            $this->freshnessSignal($responses, $documents, $verifications),
        ];

        $score = (int) round(array_sum(array_map(
            static fn (DataQualitySignal $signal): float => $signal->weightedScore(),
            $signals,
        )));

        if ($responses->isEmpty()) {
            $score = min($score, 39);
        }

        if ($verifications->contains(fn (DocumentVerification $verification): bool => $verification->isBlockingAnalysis())) {
            $score = min($score, 39);
        }

        return new DataQualityScore(
            level: $this->levelFor($score),
            score: $score,
            signals: $signals,
        );
    }

    /**
     * @param  EloquentCollection<int, QuestionnaireResponse>  $responses
     */
    private function questionnaireCompletenessSignal(EloquentCollection $responses): DataQualitySignal
    {
        $expected = 0;
        $answered = 0;

        foreach ($responses as $response) {
            $questionnaire = $response->questionnaire;
            if ($questionnaire === null) {
                continue;
            }

            $visibleQuestionIds = $this->visibleQuestionIds($response);
            $visibleMap = array_fill_keys($visibleQuestionIds, true);
            $answers = $response->answers->keyBy('question_id');

            $questions = $questionnaire->sections
                ->flatMap(fn ($section): Collection => $section->questions)
                ->filter(fn (QuestionnaireQuestion $question): bool => isset($visibleMap[(string) $question->getKey()]));

            $expected += $questions->count();

            foreach ($questions as $question) {
                $answer = $answers->get((string) $question->getKey());

                if ($answer instanceof QuestionnaireAnswer && $this->answerHasValue($question, $answer)) {
                    $answered++;
                }
            }
        }

        if ($expected === 0) {
            return new DataQualitySignal(
                key: 'questionnaire_completeness',
                label: 'Questionnaire completeness',
                score: 0,
                weight: self::WEIGHT_QUESTIONNAIRE_COMPLETENESS,
                summary: 'No questionnaire response has been submitted.',
                detail: 'Submit the active questionnaire before analysis can run.',
            );
        }

        $score = $this->percent($answered, $expected);

        return new DataQualitySignal(
            key: 'questionnaire_completeness',
            label: 'Questionnaire completeness',
            score: $score,
            weight: self::WEIGHT_QUESTIONNAIRE_COMPLETENESS,
            summary: sprintf('%d of %d visible questions answered.', $answered, $expected),
            detail: 'Conditional questions are counted only when the saved answers make them visible.',
        );
    }

    /**
     * @param  EloquentCollection<int, QuestionnaireResponse>  $responses
     */
    private function answerDocumentSupportSignal(EloquentCollection $responses): DataQualitySignal
    {
        $answered = 0;
        $supported = 0;

        foreach ($responses as $response) {
            foreach ($response->answers as $answer) {
                if (! $answer instanceof QuestionnaireAnswer || ! $answer->question instanceof QuestionnaireQuestion) {
                    continue;
                }

                if (! $this->answerHasValue($answer->question, $answer)) {
                    continue;
                }

                $answered++;

                if ($this->attachedDocumentIds($answer) !== []) {
                    $supported++;
                }
            }
        }

        if ($answered === 0) {
            return new DataQualitySignal(
                key: 'answer_document_support',
                label: 'Answer support',
                score: 0,
                weight: self::WEIGHT_ANSWER_DOCUMENT_SUPPORT,
                summary: 'No answered questions have supporting documents yet.',
                detail: 'Attach documents to the answers that will be used in analysis.',
            );
        }

        return new DataQualitySignal(
            key: 'answer_document_support',
            label: 'Answer support',
            score: $this->percent($supported, $answered),
            weight: self::WEIGHT_ANSWER_DOCUMENT_SUPPORT,
            summary: sprintf('%d of %d answered questions have attached documents.', $supported, $answered),
            detail: 'File-attachment answers count as supported when at least one clean document is attached.',
        );
    }

    /**
     * @param  EloquentCollection<int, Document>  $documents
     * @param  EloquentCollection<int, DocumentVerification>  $verifications
     */
    private function verifiedDocumentsSignal(EloquentCollection $documents, EloquentCollection $verifications): DataQualitySignal
    {
        $blocking = $verifications
            ->filter(fn (DocumentVerification $verification): bool => $verification->isBlockingAnalysis())
            ->count();

        if ($documents->isEmpty()) {
            return new DataQualitySignal(
                key: 'verified_documents',
                label: 'Verified documents',
                score: 0,
                weight: self::WEIGHT_VERIFIED_DOCUMENTS,
                summary: 'No clean documents have been uploaded yet.',
                detail: 'Upload clean supporting documents and wait for verification to complete.',
            );
        }

        $verified = $documents
            ->filter(function (Document $document): bool {
                return $document->verifications->isNotEmpty()
                    && $document->verifications->every(
                        fn (DocumentVerification $verification): bool => $verification->outcome === DocumentVerification::OUTCOME_VERIFIED,
                    );
            })
            ->count();

        if ($blocking > 0) {
            return new DataQualitySignal(
                key: 'verified_documents',
                label: 'Verified documents',
                score: 0,
                weight: self::WEIGHT_VERIFIED_DOCUMENTS,
                summary: $blocking === 1
                    ? '1 unresolved document verification flag blocks analysis.'
                    : sprintf('%d unresolved document verification flags block analysis.', $blocking),
                detail: 'Resolve advisory flags and accuracy discrepancies before relying on document-backed analysis.',
            );
        }

        return new DataQualitySignal(
            key: 'verified_documents',
            label: 'Verified documents',
            score: $this->percent($verified, $documents->count()),
            weight: self::WEIGHT_VERIFIED_DOCUMENTS,
            summary: sprintf('%d of %d clean documents are verified.', $verified, $documents->count()),
            detail: 'A document is counted as verified only when every verification claim for that document is verified.',
        );
    }

    /**
     * @param  EloquentCollection<int, QuestionnaireResponse>  $responses
     * @param  EloquentCollection<int, Document>  $documents
     * @param  EloquentCollection<int, DocumentVerification>  $verifications
     */
    private function freshnessSignal(
        EloquentCollection $responses,
        EloquentCollection $documents,
        EloquentCollection $verifications,
    ): DataQualitySignal {
        $latest = $this->latestActivity($responses, $documents, $verifications);

        if (! $latest instanceof CarbonInterface) {
            return new DataQualitySignal(
                key: 'freshness',
                label: 'Freshness',
                score: 0,
                weight: self::WEIGHT_FRESHNESS,
                summary: 'No questionnaire or document activity yet.',
                detail: 'Questionnaire and document updates keep the score fresh for analysis.',
            );
        }

        $days = (int) $latest->diffInDays(now());
        $score = match (true) {
            $days <= 30 => 100,
            $days <= 90 => 70,
            $days <= 180 => 45,
            default => 15,
        };

        return new DataQualitySignal(
            key: 'freshness',
            label: 'Freshness',
            score: $score,
            weight: self::WEIGHT_FRESHNESS,
            summary: $days === 0 ? 'Client data was updated today.' : sprintf('Last data update was %d days ago.', $days),
            detail: 'Updates within 30 days are fresh; updates older than 90 days lower analysis confidence.',
        );
    }

    /**
     * @return array<int, string>
     */
    private function visibleQuestionIds(QuestionnaireResponse $response): array
    {
        $questionnaire = $response->questionnaire;
        if ($questionnaire === null) {
            return [];
        }

        $answers = $response->answers
            ->mapWithKeys(fn (QuestionnaireAnswer $answer): array => [
                (string) $answer->question_id => [
                    'value' => $answer->value,
                    'attached_document_ids' => $this->attachedDocumentIds($answer),
                ],
            ])
            ->all();

        return $this->rules->visibleQuestionIds($questionnaire, $answers);
    }

    private function answerHasValue(QuestionnaireQuestion $question, QuestionnaireAnswer $answer): bool
    {
        if ($question->type === QuestionnaireQuestionType::FILE_ATTACH) {
            return $this->attachedDocumentIds($answer) !== [];
        }

        return ! $this->emptyValue($answer->value);
    }

    /**
     * @return array<int, string>
     */
    private function attachedDocumentIds(QuestionnaireAnswer $answer): array
    {
        $documentIds = $answer->attached_document_ids;
        if (! is_array($documentIds)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $documentId): string => trim((string) $documentId), $documentIds),
            static fn (string $documentId): bool => $documentId !== '',
        ));
    }

    private function emptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            $flat = Arr::flatten($value);

            return $flat === [] || collect($flat)->every(fn (mixed $item): bool => $this->emptyValue($item));
        }

        return false;
    }

    /**
     * @param  EloquentCollection<int, QuestionnaireResponse>  $responses
     * @param  EloquentCollection<int, Document>  $documents
     * @param  EloquentCollection<int, DocumentVerification>  $verifications
     */
    private function latestActivity(
        EloquentCollection $responses,
        EloquentCollection $documents,
        EloquentCollection $verifications,
    ): ?CarbonInterface {
        $latest = null;

        foreach ($responses as $response) {
            $latest = $this->maxDate($latest, $response->submitted_at);
            $latest = $this->maxDate($latest, $response->updated_at);

            foreach ($response->answers as $answer) {
                $latest = $this->maxDate($latest, $answer->updated_at);
            }
        }

        foreach ($documents as $document) {
            $latest = $this->maxDate($latest, $document->created_at);
            $latest = $this->maxDate($latest, $document->updated_at);
        }

        foreach ($verifications as $verification) {
            $latest = $this->maxDate($latest, $verification->verified_at);
            $latest = $this->maxDate($latest, $verification->updated_at);
        }

        return $latest;
    }

    private function maxDate(?CarbonInterface $current, mixed $candidate): ?CarbonInterface
    {
        if (! $candidate instanceof CarbonInterface) {
            return $current;
        }

        if (! $current instanceof CarbonInterface || $candidate->greaterThan($current)) {
            return $candidate;
        }

        return $current;
    }

    private function percent(int $part, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return (int) round(($part / $total) * 100);
    }

    private function levelFor(int $score): string
    {
        return match (true) {
            $score >= 85 => Client::DATA_QUALITY_HIGH,
            $score >= 65 => Client::DATA_QUALITY_MEDIUM,
            $score >= 40 => Client::DATA_QUALITY_LOW,
            default => Client::DATA_QUALITY_INSUFFICIENT,
        };
    }
}
