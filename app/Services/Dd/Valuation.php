<?php

declare(strict_types=1);

namespace App\Services\Dd;

use App\Enums\DiscountMethod;
use App\Models\BusinessValuation as BusinessValuationModel;
use App\Models\DdEngagement;
use App\Models\DdValuation;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Pv\BusinessValuation;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Facades\DB;

final class Valuation implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['dd.valuation'];
    }

    public function __construct(
        private readonly BusinessValuation $businessValuation,
        private readonly FxNormaliser $fx,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function calculate(DdEngagement $engagement, ?User $actor = null, array $options = []): DdValuation
    {
        $engagement->loadMissing('client');
        $targetDetails = $engagement->target_details ?? [];
        $sourceCurrency = strtoupper((string) ($options['source_currency'] ?? $targetDetails['currency'] ?? 'NZD'));
        $financials = $this->financialInputs($engagement, $options, $targetDetails);

        return DB::transaction(function () use ($engagement, $actor, $options, $sourceCurrency, $financials): DdValuation {
            $businessValuation = $this->businessValuation->calculate($engagement->client, [
                'industry_code' => $options['industry_code'] ?? $financials['industry_code'] ?? 'M6962',
                'growth_rate' => $options['growth_rate'] ?? 0.03,
                'terminal_growth_rate' => $options['terminal_growth_rate'] ?? 0.02,
                'discount_method' => $options['discount_method'] ?? DiscountMethod::AdvisorConfigured,
                'discount_options' => $options['discount_options'] ?? [
                    'rate' => 0.12,
                    'rationale' => 'DD valuation default discount rate.',
                ],
                'adjustments' => $options['adjustments'] ?? [],
                'force_questionnaire_financials' => true,
                'questionnaire_financials' => $financials,
            ]);

            $fx = $this->fx->normalise($businessValuation, $sourceCurrency);
            $buyerPosition = $this->buyerPosition(
                businessValuation: $businessValuation,
                fx: $fx,
                askingPrice: $options['asking_price'] ?? $financials['asking_price'] ?? null,
            );

            $valuation = DdValuation::query()->create([
                'client_id' => $engagement->client_id,
                'dd_engagement_id' => $engagement->getKey(),
                'business_valuation_id' => $businessValuation->getKey(),
                'pv_calculation_id' => $businessValuation->pv_calculation_id,
                'source_currency' => $fx['source_currency'],
                'normalised_currency' => $fx['normalised_currency'],
                'exchange_rate_id' => $fx['exchange_rate_id'],
                'source_to_nzd_rate' => $fx['source_to_nzd_rate'],
                'rate_timestamp' => $fx['rate_timestamp'],
                'normalised_values' => $fx['normalised_values'],
                'sensitivity' => $fx['sensitivity'],
                'buyer_position' => $buyerPosition,
                'source_attributions' => array_values(array_merge(
                    $businessValuation->source_attributions ?? [],
                    $fx['source_attributions'],
                )),
                'as_at' => now(),
            ]);

            $this->audit->record('dd.valuation_created', subject: $valuation, actor: $actor, after: [
                'dd_engagement_id' => $engagement->getKey(),
                'business_valuation_id' => $businessValuation->getKey(),
                'source_currency' => $sourceCurrency,
                'source_to_nzd_rate' => $fx['source_to_nzd_rate'],
                'buyer_position' => $buyerPosition['position'],
            ]);

            return $valuation->refresh()->load('businessValuation.pvCalculation');
        });
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $targetDetails
     * @return array<string, mixed>
     */
    private function financialInputs(DdEngagement $engagement, array $options, array $targetDetails): array
    {
        $financials = is_array($options['financials'] ?? null)
            ? $options['financials']
            : (is_array($targetDetails['valuation_financials'] ?? null) ? $targetDetails['valuation_financials'] : []);

        return [
            ...$financials,
            'source_reference' => $financials['source_reference'] ?? "dd_engagement:{$engagement->id}:target_financials",
            'asking_price' => $options['asking_price'] ?? $targetDetails['asking_price'] ?? null,
            'industry_code' => $options['industry_code'] ?? $targetDetails['industry_code'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $fx
     * @return array<string, mixed>
     */
    private function buyerPosition(BusinessValuationModel $businessValuation, array $fx, mixed $askingPrice): array
    {
        $rate = (float) $fx['source_to_nzd_rate'];
        $askingPriceNzd = is_numeric($askingPrice) ? round((float) $askingPrice * $rate, 2) : null;
        $low = (float) data_get($fx, 'normalised_values.reconciled.low');
        $mid = (float) data_get($fx, 'normalised_values.reconciled.mid');
        $high = (float) data_get($fx, 'normalised_values.reconciled.high');

        $position = match (true) {
            $askingPriceNzd === null => 'no_asking_price',
            $askingPriceNzd < $low => 'buyer_favourable',
            $askingPriceNzd > $high => 'renegotiate_or_walkaway',
            default => 'within_range',
        };

        return [
            'position' => $position,
            'asking_price_nzd' => $askingPriceNzd,
            'reconciled_low_nzd' => $low,
            'reconciled_mid_nzd' => $mid,
            'reconciled_high_nzd' => $high,
            'gap_to_mid_nzd' => $askingPriceNzd === null ? null : round($askingPriceNzd - $mid, 2),
            'method_count' => count(array_filter([
                $businessValuation->sde_value,
                $businessValuation->ebitda_value,
                $businessValuation->dcf_value,
            ])),
        ];
    }
}
