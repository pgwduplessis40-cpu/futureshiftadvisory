<?php

declare(strict_types=1);

namespace App\Services\Dd\Workstreams;

use App\Models\AnalysisFinding;
use App\Models\DdDataRoomItem;
use App\Models\DdEngagement;
use App\Models\Document;
use App\Models\DocumentVerification;

final class DdEvidenceAssembler
{
    /**
     * @return array{
     *     item_count:int,
     *     document_count:int,
     *     verified_documents:int,
     *     advisory_flags:int,
     *     accuracy_discrepancies:int,
     *     verification_weight:int,
     *     document_support:string,
     *     data_room_item_ids:array<int, string>,
     *     accuracy_verification_ids:array<int, string>,
     *     attributions:array<int, array{claim:string, source_reference:string}>,
     *     documents:array<int, array<string, mixed>>
     * }
     */
    public function summary(DdEngagement $engagement, string $workstream): array
    {
        $items = DdDataRoomItem::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->where('workstream', $workstream)
            ->with('document.verifications')
            ->latest()
            ->get();

        $verifiedDocuments = 0;
        $advisoryFlags = 0;
        $accuracyDiscrepancies = 0;
        $verificationWeight = 0;
        $accuracyVerificationIds = [];
        $attributions = [[
            'claim' => 'DD engagement identifies the acquisition target and buyer context.',
            'source_reference' => "dd_engagement:{$engagement->id}",
        ]];
        $documents = [];

        foreach ($items as $item) {
            $document = $item->document;
            if (! $document instanceof Document) {
                continue;
            }

            $verifications = $document->verifications;
            $hasVerifiedSet = $verifications->isNotEmpty()
                && $verifications->every(
                    fn (DocumentVerification $verification): bool => $verification->outcome === DocumentVerification::OUTCOME_VERIFIED,
                );
            $hasAdvisoryFlag = $verifications->contains(
                fn (DocumentVerification $verification): bool => $verification->outcome === DocumentVerification::OUTCOME_ADVISORY_FLAG
                    && $verification->resolved_at === null,
            );
            $accuracyFlags = $verifications->filter(
                fn (DocumentVerification $verification): bool => $verification->outcome === DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY
                    && $verification->resolved_at === null,
            );

            if ($hasVerifiedSet) {
                $verifiedDocuments++;
                $verificationWeight += 2;
            } elseif ($document->scanner_result === Document::SCANNER_CLEAN) {
                $verificationWeight++;
            }

            if ($hasAdvisoryFlag) {
                $advisoryFlags++;
            }

            foreach ($accuracyFlags as $verification) {
                $accuracyDiscrepancies++;
                $accuracyVerificationIds[] = (string) $verification->getKey();
            }

            $attributions[] = [
                'claim' => "DD data room item is scoped to the {$workstream} workstream.",
                'source_reference' => "dd_data_room_item:{$item->id}",
            ];
            $attributions[] = [
                'claim' => 'DD workstream document evidence is stored through the secure document ledger.',
                'source_reference' => "document:{$document->id}",
            ];

            foreach ($verifications as $verification) {
                $attributions[] = [
                    'claim' => "Document verification outcome is {$verification->outcome}.",
                    'source_reference' => "document_verification:{$verification->id}",
                ];
            }

            $documents[] = [
                'id' => (string) $document->id,
                'filename' => $document->original_filename,
                'scanner_result' => $document->scanner_result,
                'verification_outcomes' => $verifications
                    ->map(fn (DocumentVerification $verification): string => $verification->outcome)
                    ->values()
                    ->all(),
                'weight' => $hasVerifiedSet ? 2 : ($document->scanner_result === Document::SCANNER_CLEAN ? 1 : 0),
            ];
        }

        return [
            'item_count' => $items->count(),
            'document_count' => count($documents),
            'verified_documents' => $verifiedDocuments,
            'advisory_flags' => $advisoryFlags,
            'accuracy_discrepancies' => $accuracyDiscrepancies,
            'verification_weight' => $verificationWeight,
            'document_support' => $this->documentSupport($verifiedDocuments, $advisoryFlags, $accuracyDiscrepancies),
            'data_room_item_ids' => $items->pluck('id')->map(static fn (mixed $id): string => (string) $id)->values()->all(),
            'accuracy_verification_ids' => $accuracyVerificationIds,
            'attributions' => $attributions,
            'documents' => $documents,
        ];
    }

    private function documentSupport(int $verifiedDocuments, int $advisoryFlags, int $accuracyDiscrepancies): string
    {
        if ($accuracyDiscrepancies > 0) {
            return AnalysisFinding::DOCUMENT_SUPPORT_ACCURACY_DISCREPANCY;
        }

        if ($advisoryFlags > 0) {
            return AnalysisFinding::DOCUMENT_SUPPORT_ADVISORY_FLAG;
        }

        return $verifiedDocuments > 0
            ? AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED
            : AnalysisFinding::DOCUMENT_SUPPORT_NONE;
    }
}
