<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\PlanAssessment;

/**
 * @phpstan-import-type CriterionPlanContext from Assessment
 * @phpstan-import-type CriterionScore from Assessment
 *
 * @phpstan-type SnapshotSection array{id:string,title:string,requirement_key:string|null,attached_document_ids:list<int|string>,body:string}
 */
final class AssessmentEvidenceScopeCorrection
{
    /**
     * Recognise the case where a release adds already-submitted material to a
     * criterion's evidence map. It must be rescored, but its score difference
     * is a calibration correction rather than new founder work.
     *
     * @param  CriterionScore  $previousScore
     * @param  CriterionPlanContext  $planContext
     */
    public function applies(
        PlanAssessment $previousAssessment,
        array $previousScore,
        array $planContext,
        string $scoringContractVersion,
    ): bool {
        $metadata = is_array($previousScore['metadata'] ?? null) ? $previousScore['metadata'] : [];
        if (($metadata['scoring_contract_version'] ?? null) !== $scoringContractVersion) {
            return false;
        }

        $currentSections = collect((array) ($planContext['criterion_focus_sections'] ?? []))
            ->filter(fn (mixed $section): bool => is_array($section)
                && trim((string) ($section['section_id'] ?? '')) !== '')
            ->values();
        if ($currentSections->isEmpty()) {
            return false;
        }

        $previousSourceIds = collect((array) ($metadata['source_sections'] ?? []))
            ->filter(fn (mixed $section): bool => is_array($section))
            ->map(fn (array $section): string => (string) ($section['section_id'] ?? ''))
            ->filter()
            ->unique();
        if ($currentSections->every(
            fn (array $section): bool => $previousSourceIds->contains((string) $section['section_id']),
        )) {
            return false;
        }

        $previousSnapshotSections = collect((array) data_get($previousAssessment->plan_snapshot, 'phases', []))
            ->flatMap(function (mixed $phase): array {
                if (! is_array($phase)) {
                    return [];
                }

                return collect((array) ($phase['sections'] ?? []))
                    ->filter(fn (mixed $section): bool => is_array($section))
                    ->all();
            })
            ->filter(fn (array $section): bool => trim((string) ($section['id'] ?? '')) !== '')
            ->keyBy(fn (array $section): string => (string) $section['id']);

        return $currentSections->every(function (array $section) use ($previousSnapshotSections): bool {
            $previousSection = $previousSnapshotSections->get((string) $section['section_id']);

            return is_array($previousSection)
                && hash_equals(
                    $this->canonicalJson($this->sectionContent($previousSection)),
                    $this->canonicalJson($this->sectionContent($section)),
                );
        });
    }

    /**
     * @param  SnapshotSection  $section
     * @return array{title:string,requirement_key:string|null,attached_document_ids:list<int|string>,body:string}
     */
    private function sectionContent(array $section): array
    {
        return [
            'title' => (string) ($section['title'] ?? ''),
            'requirement_key' => isset($section['requirement_key']) ? (string) $section['requirement_key'] : null,
            'attached_document_ids' => array_values(array_filter(
                (array) ($section['attached_document_ids'] ?? []),
                fn (mixed $id): bool => is_int($id) || is_string($id),
            )),
            'body' => (string) ($section['body'] ?? ''),
        ];
    }

    /** @param array<array-key, mixed> $value */
    private function canonicalJson(array $value): string
    {
        return json_encode($this->sortForHash($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function sortForHash(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sorted = array_map(fn (mixed $item): mixed => $this->sortForHash($item), $value);
        if (! array_is_list($sorted)) {
            ksort($sorted);
        }

        return $sorted;
    }
}
