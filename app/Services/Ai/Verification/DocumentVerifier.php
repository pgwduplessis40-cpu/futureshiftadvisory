<?php

declare(strict_types=1);

namespace App\Services\Ai\Verification;

use App\Jobs\RecomputeDataQualityScore;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\User;
use App\Notifications\DocumentDiscrepancyUrgentNotification;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Prompts\PromptRegistry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class DocumentVerifier
{
    public function __construct(
        private readonly AiClient $ai,
        private readonly PromptRegistry $prompts,
    ) {}

    /**
     * @param  array<string, mixed>  $claim
     */
    public function verify(Document $document, array $claim): DocumentVerification
    {
        $claimText = $this->claimText($claim);
        $contextHash = $this->contextHash($claim, $claimText);
        $previous = DocumentVerification::query()
            ->where('document_id', $document->getKey())
            ->where('context_hash', $contextHash)
            ->first();
        $previousOutcome = $previous?->outcome;

        $verification = DocumentVerification::query()->updateOrCreate(
            [
                'document_id' => $document->getKey(),
                'context_hash' => $contextHash,
            ],
            [
                'client_id' => $document->client_id,
                'entrepreneur_profile_id' => $document->entrepreneur_profile_id,
                'questionnaire_response_id' => $claim['questionnaire_response_id'] ?? null,
                'questionnaire_answer_id' => $claim['questionnaire_answer_id'] ?? null,
                'questionnaire_question_id' => $claim['questionnaire_question_id'] ?? ($claim['question_id'] ?? null),
                'plan_section_id' => $claim['plan_section_id'] ?? null,
                'claim_source' => (string) ($claim['source'] ?? 'upload_context'),
                'question_prompt' => $this->nullableString($claim['question_prompt'] ?? null),
                'claim_text' => $claimText,
                'outcome' => DocumentVerification::OUTCOME_PENDING,
                'source_payload' => $claim,
            ],
        );

        try {
            $envelope = $this->prompts->envelope(
                id: 'document.verify',
                input: [
                    'claim' => [
                        'text' => $claimText,
                        'question_prompt' => $verification->question_prompt,
                        'source' => $verification->claim_source,
                    ],
                    'document' => $this->documentEvidence($document),
                    'expected_metadata_schema' => [
                        'verification_outcome' => DocumentVerification::outcomes(),
                        'confidence' => 'number between 0 and 1',
                        'client_explanation' => 'plain English explanation suitable for the client portal',
                    ],
                ],
                dataQualitySummary: [
                    'scanner_result' => $document->scanner_result,
                    'document_sha256' => $document->sha256,
                ],
                sourceReferences: ['document:'.$document->getKey()],
            );

            $response = $this->ai->verifyDocument($envelope);
            $outcome = $this->outcomeFrom($response);

            $verification->forceFill([
                'outcome' => $outcome,
                'confidence' => $this->confidenceFrom($response),
                'explanation' => $response->text,
                'client_explanation' => $this->clientExplanationFrom($response, $outcome),
                'ai_payload' => $response->toArray(),
                'prompt_version' => $response->promptVersion,
                'prompt_hash' => $response->promptHash,
                'verified_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            report($e);

            $verification->forceFill([
                'outcome' => DocumentVerification::OUTCOME_VERIFICATION_ERROR,
                'confidence' => null,
                'explanation' => 'Document verification failed: '.$e->getMessage(),
                'client_explanation' => 'Automated verification could not be completed yet. An advisor will review this document.',
                'verified_at' => now(),
            ])->save();
        }

        if (
            $verification->outcome === DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY
            && $previousOutcome !== DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY
        ) {
            $this->notifyAdvisors($verification->refresh());
        }

        if ($document->client_id !== null) {
            RecomputeDataQualityScore::dispatch((string) $document->client_id)->afterCommit();
        }

        return $verification->refresh();
    }

    /**
     * @param  array<string, mixed>  $claim
     */
    private function claimText(array $claim): string
    {
        $candidate = $this->nullableString($claim['claim'] ?? null)
            ?? $this->nullableString($claim['claim_value'] ?? null)
            ?? $this->nullableString($claim['question_prompt'] ?? null);

        return $candidate ?? 'Uploaded supporting document';
    }

    /**
     * @param  array<string, mixed>  $claim
     */
    private function contextHash(array $claim, string $claimText): string
    {
        return hash('sha256', json_encode([
            'source' => (string) ($claim['source'] ?? 'upload_context'),
            'question_id' => (string) ($claim['questionnaire_question_id'] ?? ($claim['question_id'] ?? '')),
            'claim' => $claimText,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function documentEvidence(Document $document): array
    {
        $bytes = Storage::disk('secure_local')->get($document->stored_path);

        return [
            'id' => $document->id,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'byte_size' => $document->byte_size,
            'sha256' => $document->sha256,
            'content_excerpt' => $this->contentExcerpt($bytes, $document->mime_type),
        ];
    }

    private function contentExcerpt(string $bytes, ?string $mimeType): ?string
    {
        $isText = str_starts_with((string) $mimeType, 'text/')
            || str_contains((string) $mimeType, 'json')
            || str_contains((string) $mimeType, 'xml')
            || mb_check_encoding($bytes, 'UTF-8');

        if (! $isText) {
            return null;
        }

        $excerpt = trim(preg_replace('/\s+/', ' ', $bytes) ?? '');

        return $excerpt === '' ? null : mb_substr($excerpt, 0, 4000);
    }

    private function outcomeFrom(AiResponse $response): string
    {
        $candidate = (string) (
            Arr::get($response->metadata, 'verification_outcome')
            ?? Arr::get($response->metadata, 'verification.outcome')
            ?? Arr::get($response->metadata, 'outcome')
            ?? ''
        );

        return in_array($candidate, DocumentVerification::outcomes(), true)
            ? $candidate
            : DocumentVerification::OUTCOME_ADVISORY_FLAG;
    }

    private function confidenceFrom(AiResponse $response): ?float
    {
        $raw = Arr::get($response->metadata, 'confidence');
        if (! is_numeric($raw)) {
            return null;
        }

        return max(0.0, min(1.0, (float) $raw));
    }

    private function clientExplanationFrom(AiResponse $response, string $outcome): string
    {
        $candidate = $this->nullableString(Arr::get($response->metadata, 'client_explanation'));
        if ($candidate !== null) {
            return $candidate;
        }

        return match ($outcome) {
            DocumentVerification::OUTCOME_VERIFIED => 'This document supports the attached claim.',
            DocumentVerification::OUTCOME_ADVISORY_FLAG => 'This document needs advisor review before related analysis is released.',
            DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY => 'This document appears to conflict with the attached claim, so related analysis is paused.',
            default => 'Verification is in progress.',
        };
    }

    private function nullableString(mixed $value): ?string
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

    private function notifyAdvisors(DocumentVerification $verification): void
    {
        User::query()
            ->whereIn('user_type', [User::TYPE_SUPER_ADMIN, User::TYPE_ADVISOR])
            ->get()
            ->each(fn (User $user): mixed => $user->notify(
                new DocumentDiscrepancyUrgentNotification($verification),
            ));
    }
}
