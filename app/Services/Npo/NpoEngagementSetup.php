<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Models\Client;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use InvalidArgumentException;

final class NpoEngagementSetup
{
    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array{sub_type:string, legal_structure:string, isa_2022_reregistered?:bool|null}  $input
     */
    public function create(Client $client, User $actor, array $input): NpoEngagement
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::from((string) $client->engagement_type);

        if ($engagementType !== EngagementType::NPO) {
            throw new InvalidArgumentException('NPO engagement setup requires an NPO client.');
        }

        $subType = NpoEngagementSubType::from($input['sub_type']);
        $legalStructure = NpoLegalStructure::from($input['legal_structure']);

        $engagement = NpoEngagement::query()->create([
            'client_id' => $client->getKey(),
            'sub_type' => $subType,
            'legal_structure' => $legalStructure,
            'isa_2022_reregistered' => $input['isa_2022_reregistered'] ?? null,
            'created_by_user_id' => $actor->getKey(),
            'updated_by_user_id' => $actor->getKey(),
        ]);

        $this->audit->record('npo_engagement.created', subject: $engagement, actor: $actor, after: [
            'client_id' => $client->getKey(),
            'sub_type' => $subType->value,
            'legal_structure' => $legalStructure->value,
            'isa_2022_reregistered' => $engagement->isa_2022_reregistered,
        ]);

        return $engagement->refresh();
    }

    public function amendLegalStructure(NpoEngagement $engagement, NpoLegalStructure $legalStructure, User $actor): NpoEngagement
    {
        $before = $engagement->legal_structure?->value;

        $engagement->forceFill([
            'legal_structure' => $legalStructure,
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record('npo_engagement.legal_structure_amended', subject: $engagement, actor: $actor, before: [
            'legal_structure' => $before,
        ], after: [
            'legal_structure' => $legalStructure->value,
        ]);

        return $engagement->refresh();
    }
}
