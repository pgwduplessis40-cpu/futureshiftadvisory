<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Ai\Verification\DocumentVerifier;
use App\Services\Audit\AuditWriter;
use App\Services\Documents\DocumentVerificationBlockedException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PlanDocuments
{
    public function __construct(
        private readonly DocumentVerifier $verifier,
        private readonly AuditWriter $audit,
    ) {}

    public function attachAndVerify(PlanSection $section, Document $document, User $actor, string $claim): DocumentVerification
    {
        $section->loadMissing('businessPlan');

        if ($document->category !== Document::CATEGORY_PLAN_ATTACHMENT) {
            throw new InvalidArgumentException('Only plan attachment documents can be attached to entrepreneur plan sections.');
        }

        $profileId = $section->businessPlan?->entrepreneur_profile_id;
        if ((string) $document->entrepreneur_profile_id !== (string) $profileId) {
            throw new InvalidArgumentException('Plan attachment must belong to the same entrepreneur profile as the plan section.');
        }

        return DB::transaction(function () use ($section, $document, $actor, $claim): DocumentVerification {
            $verification = $this->verifier->verify($document, [
                'source' => 'entrepreneur_plan_section',
                'plan_section_id' => $section->getKey(),
                'question_prompt' => $section->title,
                'claim' => $claim,
            ]);
            $attached = collect($section->attached_document_ids ?? [])
                ->map(fn (mixed $id): string => (string) $id)
                ->push((string) $document->getKey())
                ->unique()
                ->values()
                ->all();
            $metadata = is_array($section->metadata) ? $section->metadata : [];

            $section->forceFill([
                'attached_document_ids' => $attached,
                'metadata' => [
                    ...$metadata,
                    'document_verification' => [
                        'latest_verification_id' => $verification->getKey(),
                        'latest_outcome' => $verification->outcome,
                    ],
                ],
            ])->save();

            $this->audit->record('entrepreneur.plan_document_verified', subject: $verification, actor: $actor, after: [
                'plan_section_id' => $section->getKey(),
                'document_id' => $document->getKey(),
                'outcome' => $verification->outcome,
            ]);

            return $verification->refresh();
        });
    }

    public function criterionScoreWithDocumentSupport(PlanSection $section, int $baseScore): int
    {
        $verifiedCount = $this->verifications($section)
            ->where('outcome', DocumentVerification::OUTCOME_VERIFIED)
            ->count();
        $advisoryFlags = $this->verifications($section)
            ->whereIn('outcome', [
                DocumentVerification::OUTCOME_ADVISORY_FLAG,
                DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY,
            ])
            ->whereNull('resolved_at')
            ->count();

        return max(0, min(100, $baseScore + ($verifiedCount * 8) - ($advisoryFlags * 12)));
    }

    public function ensureScoringClear(PlanSection $section): void
    {
        $flags = $this->verifications($section)
            ->whereIn('outcome', [
                DocumentVerification::OUTCOME_ADVISORY_FLAG,
                DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY,
            ])
            ->whereNull('resolved_at')
            ->values();

        if ($flags->isNotEmpty()) {
            throw new DocumentVerificationBlockedException($flags);
        }
    }

    /**
     * @return Collection<int, DocumentVerification>
     */
    private function verifications(PlanSection $section): Collection
    {
        $documentIds = collect($section->attached_document_ids ?? [])
            ->map(fn (mixed $id): string => (string) $id)
            ->filter()
            ->values();

        if ($documentIds->isEmpty()) {
            return collect();
        }

        return DocumentVerification::query()
            ->whereIn('document_id', $documentIds)
            ->where('plan_section_id', $section->getKey())
            ->get();
    }
}
