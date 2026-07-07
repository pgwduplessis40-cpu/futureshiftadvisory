<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Enums\DiscountMethod;
use App\Enums\PvType;
use App\Models\Client;
use App\Models\ImprovementOpportunity;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Facades\DB;

final class ImprovementPv implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['pv.improvement'];
    }

    public function __construct(private readonly PvEngine $pv) {}

    /**
     * @param  array<int, array<string, mixed>>  $opportunities
     * @param  array<string, mixed>  $discountOptions
     * @return array<int, ImprovementOpportunity>
     */
    public function rank(
        Client $client,
        array $opportunities,
        DiscountMethod $discountMethod = DiscountMethod::AdvisorConfigured,
        array $discountOptions = ['rate' => 0.12, 'rationale' => 'Advisor default improvement PV discount rate.'],
    ): array {
        return DB::transaction(function () use ($client, $opportunities, $discountMethod, $discountOptions): array {
            $models = [];
            $fingerprints = [];

            foreach ($opportunities as $opportunity) {
                $fingerprints[] = $this->sourceFingerprint($opportunity);
            }

            $fingerprints = array_values(array_unique($fingerprints));

            if ($fingerprints !== []) {
                ImprovementOpportunity::query()
                    ->where('client_id', $client->getKey())
                    ->whereIn('source_fingerprint', $fingerprints)
                    ->whereNull('superseded_at')
                    ->update([
                        'superseded_at' => now(),
                        'superseded_reason' => 're_ranked',
                    ]);
            }

            foreach ($opportunities as $opportunity) {
                $annualBenefit = (float) ($opportunity['annual_benefit'] ?? 0);
                $durationYears = max(1, min(10, (int) ($opportunity['duration_years'] ?? 1)));
                $cashFlows = array_fill(1, $durationYears, $annualBenefit);

                $calculation = $this->pv->calculate(
                    client: $client,
                    type: PvType::ImprovementOpportunity,
                    discountMethod: $discountMethod,
                    cashFlows: $cashFlows,
                    discountOptions: $discountOptions,
                );

                $models[] = ImprovementOpportunity::query()->create([
                    'client_id' => $client->getKey(),
                    'analysis_finding_id' => $opportunity['analysis_finding_id'] ?? null,
                    'pv_calculation_id' => $calculation->getKey(),
                    'title' => (string) ($opportunity['title'] ?? 'Improvement opportunity'),
                    'annual_benefit' => $annualBenefit,
                    'duration_years' => $durationYears,
                    'pv_of_impact' => (float) $calculation->result['present_value'],
                    'rank' => 0,
                    'source_fingerprint' => $this->sourceFingerprint($opportunity),
                    'source_attributions' => $this->sourceAttributions($opportunity, $calculation->source_attributions),
                ]);
            }

            return $this->rankModels($models);
        });
    }

    /**
     * @param  array<string, mixed>  $opportunity
     * @param  array<int, array{claim:string, source_reference:string}>  $calculationAttributions
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function sourceAttributions(array $opportunity, array $calculationAttributions): array
    {
        $source = (string) ($opportunity['source_reference'] ?? 'advisor:improvement_opportunity');

        return [
            [
                'claim' => 'Improvement opportunity benefit and duration were supplied for PV ranking.',
                'source_reference' => $source,
            ],
            ...$calculationAttributions,
        ];
    }

    /**
     * @param  array<string, mixed>  $opportunity
     */
    private function sourceFingerprint(array $opportunity): string
    {
        return hash('sha256', implode('|', [
            mb_strtolower(trim((string) ($opportunity['title'] ?? 'Improvement opportunity'))),
            $this->fingerprintSource($opportunity, 'advisor:improvement_opportunity'),
        ]));
    }

    /**
     * @param  array<string, mixed>  $opportunity
     */
    private function fingerprintSource(array $opportunity, string $default): string
    {
        $source = (string) (
            $opportunity['source_fingerprint_key']
            ?? $opportunity['stable_source_reference']
            ?? $opportunity['source_reference']
            ?? $default
        );

        return mb_strtolower(trim($source)) ?: $default;
    }

    /**
     * @param  array<int, ImprovementOpportunity>  $models
     * @return array<int, ImprovementOpportunity>
     */
    private function rankModels(array $models): array
    {
        usort($models, fn (ImprovementOpportunity $a, ImprovementOpportunity $b): int => $b->pv_of_impact <=> $a->pv_of_impact);

        foreach ($models as $index => $model) {
            $model->forceFill(['rank' => $index + 1])->save();
            $models[$index] = $model->refresh();
        }

        return $models;
    }
}
