<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Services\Integration\Ird\Contracts\IrdClient;
use App\Services\Integration\NzParliament\Contracts\NzParliamentClient;
use App\Services\Integration\WorkSafe\Contracts\WorkSafeClient;
use Illuminate\Support\Carbon;

final class LegislativeCurrencyMonitor
{
    public const LAYER_ID = 14;

    public function __construct(
        private readonly NzParliamentClient $parliament,
        private readonly WorkSafeClient $workSafe,
        private readonly IrdClient $ird,
    ) {}

    /**
     * @return array{changes_seen:int, candidates_created:int}
     */
    public function run(?Carbon $ranAt = null): array
    {
        $ranAt ??= now();
        $changes = $this->changes();
        $created = 0;

        foreach ($changes as $change) {
            $changeKey = $this->changeKey($change);

            $exists = LearningUpdate::query()
                ->where('layer_id', self::LAYER_ID)
                ->where('source->change_key', $changeKey)
                ->exists();

            if ($exists) {
                continue;
            }

            LearningUpdate::query()->create([
                'layer_id' => self::LAYER_ID,
                'source' => [
                    'type' => 'legislative_currency_monitor',
                    'source' => $change['source'] ?? 'unknown',
                    'change_key' => $changeKey,
                    'source_url' => $change['source_url'] ?? null,
                ],
                'summary' => (string) ($change['summary'] ?? $change['title'] ?? 'Legislative change detected.'),
                'proposed_change' => [
                    'action' => 'review_compliance_checker_statute_currency',
                    'statute' => $change['statute'] ?? null,
                    'automatic_application' => false,
                ],
                'impact_scope' => [
                    'modules' => ['compliance'],
                    'requires_advisor_review' => true,
                ],
                'clients_affected' => 0,
                'magnitude' => 'medium',
                'confidence' => 0.82,
                'evidence' => [$change],
                'effective_date' => isset($change['effective_date']) ? Carbon::parse((string) $change['effective_date']) : null,
                'status' => LearningUpdate::STATUS_DETECTED,
            ]);

            $created++;
        }

        LearningLayerRun::query()->create([
            'layer_id' => self::LAYER_ID,
            'ran_at' => $ranAt,
            'candidates_created' => $created,
            'window' => ['changes_seen' => count($changes)],
            'status' => LearningLayerRun::STATUS_COMPLETED,
        ]);

        return [
            'changes_seen' => count($changes),
            'candidates_created' => $created,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function changes(): array
    {
        return [
            ...$this->parliament->legislativeChanges(),
            ...$this->workSafe->legislativeChanges(),
            ...$this->ird->legislativeChanges(),
        ];
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function changeKey(array $change): string
    {
        $explicit = trim((string) ($change['change_key'] ?? ''));

        if ($explicit !== '') {
            return $explicit;
        }

        return hash('sha256', implode('|', [
            $change['source'] ?? 'unknown',
            $change['statute'] ?? 'unknown',
            $change['title'] ?? 'untitled',
            $change['effective_date'] ?? 'unknown',
        ]));
    }
}
