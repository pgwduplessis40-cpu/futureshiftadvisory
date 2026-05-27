<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Models\Funder;
use App\Models\LearningUpdate;
use App\Services\Learning\LayerCadenceRegistry;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class FunderRegistry
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function upsertFromLearningUpdate(LearningUpdate $update, array $input): Funder
    {
        $this->assertApprovedLayer34Update($update);

        /** @var Funder $funder */
        $funder = Funder::query()->updateOrCreate(
            ['name' => (string) $input['name']],
            [
                'type' => (string) $input['type'],
                'funding_windows' => array_values((array) ($input['funding_windows'] ?? [])),
                'criteria' => (array) ($input['criteria'] ?? []),
                'reporting_requirements' => (array) ($input['reporting_requirements'] ?? []),
                'renewal_intelligence' => (array) ($input['renewal_intelligence'] ?? []),
                'last_verified_at' => isset($input['last_verified_at'])
                    ? Carbon::parse((string) $input['last_verified_at'])
                    : now(),
                'source_learning_update_id' => $update->getKey(),
            ],
        );

        return $funder->refresh();
    }

    private function assertApprovedLayer34Update(LearningUpdate $update): void
    {
        if ((int) $update->layer_id !== LayerCadenceRegistry::LAYER_NPO_FUNDER_DATABASE_UPDATES) {
            throw new InvalidArgumentException('Funder registry updates must come from Layer 34.');
        }

        if (! in_array($update->status, [LearningUpdate::STATUS_APPROVED, LearningUpdate::STATUS_IMPLEMENTED], true)) {
            throw new InvalidArgumentException('Funder registry updates require an approved learning candidate.');
        }
    }
}
