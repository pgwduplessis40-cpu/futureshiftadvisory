<?php

declare(strict_types=1);

namespace App\Services\Dd\Workstreams;

use App\Models\DdEngagement;
use App\Services\Analysis\HolidaysActLiabilityCalculator;
use App\Services\Integration\Iponz\Contracts\IponzClient;
use App\Services\Integration\Ird\Contracts\IrdClient;
use App\Services\Integration\Linz\Contracts\LinzClient;
use App\Services\Integration\Ppsr\Contracts\PpsrClient;

final class DdNzCheckProvider
{
    public function __construct(
        private readonly PpsrClient $ppsr,
        private readonly LinzClient $linz,
        private readonly IponzClient $iponz,
        private readonly IrdClient $ird,
        private readonly HolidaysActLiabilityCalculator $holidaysAct,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(DdEngagement $engagement, string $workstream): array
    {
        $targetDetails = $engagement->target_details ?? [];
        $targetNzbn = (string) ($targetDetails['nzbn'] ?? $engagement->client?->nzbn ?? '');
        $targetName = (string) $engagement->target_name;
        $targetAddress = is_scalar($targetDetails['address'] ?? null) ? (string) $targetDetails['address'] : null;
        $checks = [];

        if (in_array($workstream, ['legal', 'nz_regulatory'], true)) {
            $checks['ppsr'] = [
                'records' => $this->ppsr->securityInterests($targetNzbn),
                'source_reference' => "ppsr:{$targetNzbn}",
            ];
            $checks['linz'] = [
                'records' => $this->linz->titleInterests($targetNzbn, $targetAddress),
                'source_reference' => "linz:{$targetNzbn}",
            ];
            $checks['iponz'] = [
                'records' => $this->iponz->intellectualProperty($targetName, $targetNzbn),
                'source_reference' => 'iponz:'.strtolower(str_replace(' ', '-', $targetName)),
            ];
        }

        if (in_array($workstream, ['tax', 'nz_regulatory'], true)) {
            $checks['ird_gst'] = [
                'record' => $this->ird->gstStatus($targetNzbn),
                'source_reference' => "ird:gst:{$targetNzbn}",
            ];
        }

        if (in_array($workstream, ['hr_people', 'nz_regulatory'], true)) {
            $checks['holidays_act'] = [
                'estimate' => $this->holidaysAct->calculate(0, 0),
                'source_reference' => 'statute:nz:holidays-act-2003',
            ];
        }

        if (in_array($workstream, ['commercial_market', 'operational', 'nz_regulatory'], true)) {
            $checks['owner_dependency'] = [
                'score' => $this->ownerDependencyScore($targetDetails),
                'source_reference' => "dd_engagement:{$engagement->id}:target_details",
            ];
        }

        return $checks;
    }

    /**
     * @param  array<string, mixed>  $targetDetails
     */
    private function ownerDependencyScore(array $targetDetails): int
    {
        $notes = strtolower((string) ($targetDetails['notes'] ?? ''));

        return match (true) {
            str_contains($notes, 'owner dependent'), str_contains($notes, 'key person') => 80,
            str_contains($notes, 'manager'), str_contains($notes, 'delegated') => 35,
            default => 50,
        };
    }
}
