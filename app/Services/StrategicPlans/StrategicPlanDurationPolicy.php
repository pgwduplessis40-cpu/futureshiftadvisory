<?php

declare(strict_types=1);

namespace App\Services\StrategicPlans;

use App\Models\FeeCalculation;
use App\Models\Proposal;

final class StrategicPlanDurationPolicy
{
    public const BAND_STANDARD = 'standard';

    public const BAND_COMPLEX = 'complex';

    public const BAND_TRANSFORMATIONAL = 'transformational';

    public const MIN_MONTHS = 12;

    private const MONTHS_BY_BAND = [
        self::BAND_STANDARD => 12,
        self::BAND_COMPLEX => 24,
        self::BAND_TRANSFORMATIONAL => 36,
    ];

    private const LABELS_BY_BAND = [
        self::BAND_STANDARD => 'Standard complexity',
        self::BAND_COMPLEX => 'Complex',
        self::BAND_TRANSFORMATIONAL => 'Transformational',
    ];

    /**
     * @return array{months:int,label:string,complexity_band:string,complexity_label:string,rationale:array<int,string>}
     */
    public function forFeeCalculation(FeeCalculation $feeCalculation): array
    {
        $feeCalculation->loadMissing('integrationScope');

        $band = self::BAND_STANDARD;
        $rationale = ['Minimum strategic plan duration is 12 months.'];
        $amount = (float) $feeCalculation->suggested_mid;
        $amountBand = $this->bandForAmount($amount);

        if ($amountBand !== self::BAND_STANDARD) {
            $band = $this->highestBand($band, $amountBand);
            $rationale[] = 'Fee midpoint is NZD '.number_format($amount, 0).' ex GST.';
        }

        $complexityLevel = data_get($feeCalculation->justification, 'complexity.level');
        $levelBand = $this->bandForComplexityLevel($complexityLevel);

        if ($levelBand !== null) {
            $band = $this->highestBand($band, $levelBand);
            $rationale[] = 'Fee model complexity is '.str_replace('_', ' ', (string) $complexityLevel).'.';
        }

        $multiplier = data_get($feeCalculation->justification, 'complexity.multiplier');
        $multiplierBand = $this->bandForComplexityMultiplier($multiplier);

        if ($multiplierBand !== null) {
            $band = $this->highestBand($band, $multiplierBand);
            $rationale[] = 'Complexity multiplier is '.number_format((float) $multiplier, 2).'.';
        }

        $integrationBand = data_get($feeCalculation->integrationScope?->computed, 'complexity_band')
            ?? data_get($feeCalculation->justification, 'complexity_band')
            ?? data_get($feeCalculation->justification, 'integration.complexity_band');
        $mappedIntegrationBand = $this->bandForIntegrationComplexity($integrationBand);

        if ($mappedIntegrationBand !== null) {
            $band = $this->highestBand($band, $mappedIntegrationBand);
            $rationale[] = 'Integration complexity band is '.(string) $integrationBand.'.';
        }

        return $this->recommendation($band, $rationale);
    }

    /**
     * @return array{months:int,label:string,complexity_band:string,complexity_label:string,rationale:array<int,string>}
     */
    public function forProposal(Proposal $proposal): array
    {
        $proposal->loadMissing('feeCalculation.integrationScope');

        $recommendation = $proposal->feeCalculation instanceof FeeCalculation
            ? $this->forFeeCalculation($proposal->feeCalculation)
            : $this->recommendation(self::BAND_STANDARD, ['Minimum strategic plan duration is 12 months.']);

        $storedMonths = $this->firstNumeric([
            data_get($proposal->scope, 'strategic_plan_duration.months'),
            data_get($proposal->scope, 'term_months'),
            data_get($proposal->acceptance_terms, 'strategic_plan_duration.months'),
            data_get($proposal->acceptance_terms, 'term_months'),
            data_get($proposal->feeCalculation?->justification, 'retainer.months'),
            data_get($proposal->feeCalculation?->justification, 'retainer_months'),
        ]);

        $months = $recommendation['months'];
        $rationale = $recommendation['rationale'];

        if ($storedMonths !== null) {
            $storedMonths = $this->normaliseMonths($storedMonths);
            $months = max($months, $storedMonths);

            if ($storedMonths > $recommendation['months']) {
                $rationale[] = 'Advisor-selected proposal term is '.$storedMonths.' months.';
            }
        }

        $band = $this->highestBand($recommendation['complexity_band'], $this->bandForMonths($months));

        return [
            'months' => $months,
            'label' => $this->labelForMonths($months),
            'complexity_band' => $band,
            'complexity_label' => $this->complexityLabel($band),
            'rationale' => $this->uniqueRationale($rationale),
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    public function applyToScope(FeeCalculation $feeCalculation, array $scope): array
    {
        $recommendation = $this->forFeeCalculation($feeCalculation);
        $explicitMonths = $this->firstNumeric([$scope['term_months'] ?? null]);
        $months = $recommendation['months'];
        $rationale = $recommendation['rationale'];

        if ($explicitMonths !== null) {
            $explicitMonths = $this->normaliseMonths($explicitMonths);
            $months = max($months, $explicitMonths);

            if ($explicitMonths > $recommendation['months']) {
                $rationale[] = 'Advisor-selected proposal term is '.$explicitMonths.' months.';
            }
        }

        $band = $this->highestBand($recommendation['complexity_band'], $this->bandForMonths($months));
        $duration = [
            'months' => $months,
            'label' => $this->labelForMonths($months),
            'complexity_band' => $band,
            'complexity_label' => $this->complexityLabel($band),
            'rationale' => $this->uniqueRationale($rationale),
        ];

        $scope['term_months'] = $months;
        $scope['strategic_plan_duration'] = $duration;

        return $scope;
    }

    public function termMonthsForProposal(Proposal $proposal): int
    {
        return $this->forProposal($proposal)['months'];
    }

    public function labelForMonths(int $months): string
    {
        $months = $this->normaliseMonths($months);

        return $months.' '.($months === 1 ? 'month' : 'months');
    }

    public function complexityLabel(string $band): string
    {
        return self::LABELS_BY_BAND[$band] ?? self::LABELS_BY_BAND[self::BAND_STANDARD];
    }

    /**
     * @param  array<int, string>  $rationale
     * @return array{months:int,label:string,complexity_band:string,complexity_label:string,rationale:array<int,string>}
     */
    private function recommendation(string $band, array $rationale): array
    {
        $band = array_key_exists($band, self::MONTHS_BY_BAND) ? $band : self::BAND_STANDARD;
        $months = self::MONTHS_BY_BAND[$band];

        return [
            'months' => $months,
            'label' => $this->labelForMonths($months),
            'complexity_band' => $band,
            'complexity_label' => $this->complexityLabel($band),
            'rationale' => $this->uniqueRationale($rationale),
        ];
    }

    private function bandForAmount(float $amount): string
    {
        return match (true) {
            $amount >= 40_000 => self::BAND_TRANSFORMATIONAL,
            $amount >= 18_000 => self::BAND_COMPLEX,
            default => self::BAND_STANDARD,
        };
    }

    private function bandForComplexityLevel(mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        return match (strtolower(trim((string) $value))) {
            'very_high', 'transformational', 'enterprise' => self::BAND_TRANSFORMATIONAL,
            'high', 'complex' => self::BAND_COMPLEX,
            'low', 'standard', 'medium' => self::BAND_STANDARD,
            default => null,
        };
    }

    private function bandForComplexityMultiplier(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $multiplier = (float) $value;

        return match (true) {
            $multiplier >= 1.45 => self::BAND_TRANSFORMATIONAL,
            $multiplier >= 1.2 => self::BAND_COMPLEX,
            default => null,
        };
    }

    private function bandForIntegrationComplexity(mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        return match (strtoupper(trim((string) $value))) {
            'XL', 'H', 'HIGH', 'VERY_HIGH', 'TRANSFORMATIONAL', 'ENTERPRISE' => self::BAND_TRANSFORMATIONAL,
            'M', 'MEDIUM', 'MODERATE', 'L', 'LARGE', 'COMPLEX' => self::BAND_COMPLEX,
            'S', 'SMALL', 'LOW', 'STANDARD' => self::BAND_STANDARD,
            default => null,
        };
    }

    private function bandForMonths(int $months): string
    {
        return match (true) {
            $months >= self::MONTHS_BY_BAND[self::BAND_TRANSFORMATIONAL] => self::BAND_TRANSFORMATIONAL,
            $months >= self::MONTHS_BY_BAND[self::BAND_COMPLEX] => self::BAND_COMPLEX,
            default => self::BAND_STANDARD,
        };
    }

    private function highestBand(string $left, string $right): string
    {
        return $this->rank($right) > $this->rank($left) ? $right : $left;
    }

    private function rank(string $band): int
    {
        return match ($band) {
            self::BAND_TRANSFORMATIONAL => 3,
            self::BAND_COMPLEX => 2,
            default => 1,
        };
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function firstNumeric(array $values): ?int
    {
        foreach ($values as $value) {
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function normaliseMonths(int $months): int
    {
        return max(self::MIN_MONTHS, $months);
    }

    /**
     * @param  array<int, string>  $rationale
     * @return array<int, string>
     */
    private function uniqueRationale(array $rationale): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $item): string => trim($item),
            $rationale,
        ))));
    }
}
