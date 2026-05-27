<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Models\LearningUpdate;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\NpoFunders\Contracts\NpoFunderSourceClient;
use App\Services\Learning\LayerCadenceRegistry;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class NpoFunderIntegration
{
    public const SOURCE_COMMUNITY_MATTERS_COGS = 'community_matters_cogs';

    public const SOURCE_COMMUNITY_MATTERS_LOTTERY = 'community_matters_lottery';

    public const SOURCE_GENEROSITY_NZ = 'generosity_nz';

    public const SOURCE_FUNDSORTER = 'fundsorter';

    public const SOURCE_TE_PUNI_KOKIRI = 'te_puni_kokiri';

    /**
     * @var array<int, string>
     */
    public const SOURCES = [
        self::SOURCE_COMMUNITY_MATTERS_COGS,
        self::SOURCE_COMMUNITY_MATTERS_LOTTERY,
        self::SOURCE_GENEROSITY_NZ,
        self::SOURCE_FUNDSORTER,
        self::SOURCE_TE_PUNI_KOKIRI,
    ];

    public function __construct(
        private readonly NpoFunderSourceClient $client,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<int, string>|null  $sources
     * @return Collection<int, LearningUpdate>
     */
    public function sync(?array $sources = null, ?User $actor = null): Collection
    {
        $updates = collect();

        foreach ($this->sources($sources) as $source) {
            $payload = $this->client->fetch($source);

            foreach ($this->funders($payload) as $funder) {
                $updates->push($this->candidateFor($source, $payload, $funder, $actor));
            }
        }

        return $updates;
    }

    /**
     * @param  array<int, string>|null  $sources
     * @return array<int, string>
     */
    private function sources(?array $sources): array
    {
        $sources ??= self::SOURCES;
        $normalised = array_values(array_unique(array_map(
            fn (string $source): string => strtolower(trim($source)),
            $sources,
        )));

        foreach ($normalised as $source) {
            if (! in_array($source, self::SOURCES, true)) {
                throw new InvalidArgumentException("Unsupported NPO funder integration source [{$source}].");
            }
        }

        return $normalised;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function funders(array $payload): array
    {
        $funders = $payload['funders'] ?? [];

        return array_values(array_filter(is_array($funders) ? $funders : [], 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $sourcePayload
     * @param  array<string, mixed>  $funder
     */
    private function candidateFor(string $source, array $sourcePayload, array $funder, ?User $actor): LearningUpdate
    {
        $funderPayload = $this->funderPayload($funder);
        $name = (string) $funderPayload['name'];

        $update = LearningUpdate::query()->create([
            'layer_id' => LayerCadenceRegistry::LAYER_NPO_FUNDER_DATABASE_UPDATES,
            'source' => [
                'type' => 'npo_funder_integration',
                'provider' => $source,
                'source_badge' => (string) ($sourcePayload['source_badge'] ?? 'stub'),
                'correlation_id' => $sourcePayload['correlation_id'] ?? null,
            ],
            'summary' => "Review NPO funder source: {$name}",
            'proposed_change' => [
                'action' => 'update_funder_registry',
                'automatic_application' => false,
                'funder' => $funderPayload,
            ],
            'impact_scope' => [
                'surface' => 'funder_registry',
                'tenant_scope' => 'global',
            ],
            'clients_affected' => 0,
            'magnitude' => 'low',
            'confidence' => $this->confidenceFor($sourcePayload),
            'evidence' => [
                'source_reference' => "npo_funders:{$source}",
                'source_payload' => $funder,
                'degraded' => (bool) ($sourcePayload['degraded'] ?? false),
                'manual_entry' => (bool) ($sourcePayload['manual_entry'] ?? false),
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        $this->audit->record('npo.funder_integration_candidate_detected', subject: $update, actor: $actor, after: [
            'provider' => $source,
            'funder_name' => $name,
            'degraded' => (bool) ($sourcePayload['degraded'] ?? false),
            'automatic_application' => false,
        ]);

        return $update->refresh();
    }

    /**
     * @param  array<string, mixed>  $funder
     * @return array<string, mixed>
     */
    private function funderPayload(array $funder): array
    {
        return [
            'name' => (string) ($funder['name'] ?? 'Unnamed NPO funder'),
            'type' => (string) ($funder['type'] ?? 'community'),
            'funding_windows' => array_values((array) ($funder['funding_windows'] ?? [])),
            'criteria' => (array) ($funder['criteria'] ?? []),
            'reporting_requirements' => (array) ($funder['reporting_requirements'] ?? []),
            'renewal_intelligence' => (array) ($funder['renewal_intelligence'] ?? []),
            'last_verified_at' => (string) ($funder['last_verified_at'] ?? now()->toIso8601String()),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function confidenceFor(array $payload): float
    {
        if ((bool) ($payload['degraded'] ?? false)) {
            return 0.62;
        }

        if ((bool) ($payload['manual_entry'] ?? false)) {
            return 0.7;
        }

        return 0.82;
    }
}
