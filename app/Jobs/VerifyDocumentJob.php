<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Document;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireQuestion;
use App\Services\Ai\Verification\DocumentVerifier;
use App\Support\RequestContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

final class VerifyDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $documentId,
        public readonly array $context = [],
    ) {}

    public function handle(DocumentVerifier $verifier, RequestContext $context): void
    {
        $context->apply('system', []);

        $document = Document::query()->find($this->documentId);

        if (! $document instanceof Document || $document->scanner_result !== Document::SCANNER_CLEAN) {
            return;
        }

        foreach ($this->claimsFor($document) as $claim) {
            $verifier->verify($document, $claim);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function claimsFor(Document $document): array
    {
        $claims = $this->claimsFromContext();

        if ($claims !== []) {
            return $claims;
        }

        $claims = $this->claimsFromQuestionnaireAnswers($document);

        if ($claims !== []) {
            return $claims;
        }

        return [[
            'source' => 'document_upload',
            'claim' => 'Uploaded supporting document',
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function claimsFromContext(): array
    {
        $claims = [];

        if (is_array($this->context['claims'] ?? null)) {
            foreach ($this->context['claims'] as $claim) {
                if (is_array($claim)) {
                    $claims[] = $claim;
                }
            }
        }

        $questionId = $this->scalarString($this->context['question_id'] ?? null);
        $claimValue = $this->normaliseClaimValue($this->context['claim_value'] ?? null);
        $questionPrompt = $this->scalarString($this->context['question_prompt'] ?? null);

        if ($questionId !== null || $claimValue !== null || $questionPrompt !== null) {
            $question = $questionId === null ? null : QuestionnaireQuestion::query()->find($questionId);

            $claims[] = [
                'source' => 'upload_context',
                'questionnaire_question_id' => $questionId,
                'question_prompt' => $questionPrompt ?? $question?->prompt,
                'claim' => $claimValue ?? $questionPrompt ?? $question?->prompt,
            ];
        }

        return array_values(array_filter(
            $claims,
            fn (array $claim): bool => $this->normaliseClaimValue($claim['claim'] ?? null) !== null,
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function claimsFromQuestionnaireAnswers(Document $document): array
    {
        return QuestionnaireAnswer::query()
            ->with(['question', 'response'])
            ->whereJsonContains('attached_document_ids', (string) $document->getKey())
            ->get()
            ->map(function (QuestionnaireAnswer $answer): array {
                return [
                    'source' => 'questionnaire_answer',
                    'questionnaire_response_id' => $answer->response_id,
                    'questionnaire_answer_id' => $answer->id,
                    'questionnaire_question_id' => $answer->question_id,
                    'question_prompt' => $answer->question?->prompt,
                    'claim' => $this->normaliseClaimValue($answer->value) ?? $answer->question?->prompt,
                ];
            })
            ->filter(fn (array $claim): bool => $this->normaliseClaimValue($claim['claim'] ?? null) !== null)
            ->values()
            ->all();
    }

    private function normaliseClaimValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', Arr::flatten($value)));
        }

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function scalarString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
