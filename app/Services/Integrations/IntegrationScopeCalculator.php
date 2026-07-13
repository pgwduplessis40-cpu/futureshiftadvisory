<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\IntegrationFeeBand;
use App\Models\IntegrationScope;
use InvalidArgumentException;

final class IntegrationScopeCalculator
{
    private const OCCURRENCES_PER_YEAR = [
        'day' => 260,
        'week' => 52,
        'month' => 12,
    ];

    private const API_POINTS = [
        'rest_public' => 1,
        'rest_partner' => 2,
        'webhook' => 2,
        'csv_export' => 3,
        'none' => 5,
    ];

    /**
     * @param  array<int, IntegrationFeeBand>  $feeBands
     * @return array<string, mixed>
     */
    public function calculate(IntegrationScope $scope, array $feeBands): array
    {
        $systems = $this->rows($scope->systems);
        $tasks = $this->rows($scope->tasks);
        $connections = $this->rows($scope->connections);
        $systemsByReference = $this->systemsByReference($systems);

        $taskRows = [];
        $annualHours = 0.0;
        $annualCost = 0.0;
        $guessCount = 0;
        $confidenceCount = 0;

        foreach ($tasks as $index => $task) {
            $minutes = $this->nonNegativeNumber($task['minutes_per_occurrence'] ?? 0, 'minutes_per_occurrence');
            $people = $this->nonNegativeNumber($task['people_count'] ?? 0, 'people_count');
            $hourlyCost = $this->nonNegativeNumber($task['hourly_cost'] ?? 0, 'hourly_cost');
            $frequency = (string) ($task['occurrences_per'] ?? '');
            $occurrences = self::OCCURRENCES_PER_YEAR[$frequency] ?? null;

            if ($occurrences === null) {
                throw new InvalidArgumentException('Task occurrences_per must be day, week, or month.');
            }

            $hours = round(($minutes * $occurrences * $people) / 60, 2);
            $cost = round($hours * $hourlyCost, 2);
            $annualHours += $hours;
            $annualCost += $cost;
            $confidence = (string) ($task['confidence'] ?? 'estimate');
            $confidenceCount++;
            $guessCount += $confidence === 'guess' ? 1 : 0;

            $taskRows[] = [
                'id' => (string) ($task['id'] ?? $index + 1),
                'description' => (string) ($task['description'] ?? 'Duplicate entry task'),
                'annual_hours_wasted' => $hours,
                'annual_cost_wasted' => $cost,
                'confidence' => $confidence,
                'source' => (string) ($task['source'] ?? 'manual'),
                'source_reference' => $task['source_reference'] ?? null,
                'claim' => $task['claim'] ?? null,
            ];
        }

        $connectionRows = [];
        $complexityScore = 0;
        $noApi = false;
        foreach ($connections as $index => $connection) {
            $from = $this->systemForReference($systemsByReference, $connection['from_system'] ?? null);
            $to = $this->systemForReference($systemsByReference, $connection['to_system'] ?? null);
            $apiQuality = $this->worstApiQuality($from, $to);
            $apiPoints = self::API_POINTS[$apiQuality] ?? self::API_POINTS['none'];
            $direction = (string) ($connection['direction'] ?? 'one_way');
            $transform = (string) ($connection['transform_complexity'] ?? 'low');
            $authPoints = $this->hasOauth($from, $to) ? 1 : 0;
            $volumePoints = max((int) ($from['monthly_records'] ?? 0), (int) ($to['monthly_records'] ?? 0)) > 10_000 ? 1 : 0;
            $transformPoints = match ($transform) {
                'low' => 0,
                'med' => 1,
                'high' => 3,
                default => throw new InvalidArgumentException('Connection transform_complexity must be low, med, or high.'),
            };
            $score = ($apiPoints + $transformPoints + $authPoints + $volumePoints) * ($direction === 'two_way' ? 2 : 1);
            $complexityScore += $score;
            $noApi = $noApi || $apiQuality === 'none';

            $connectionRows[] = [
                'id' => (string) ($connection['id'] ?? $index + 1),
                'from_system' => $connection['from_system'] ?? null,
                'to_system' => $connection['to_system'] ?? null,
                'score' => $score,
                'drivers' => [
                    'api_quality' => $apiQuality,
                    'direction' => $direction,
                    'transform_complexity' => $transform,
                    'oauth' => $authPoints === 1,
                    'high_volume' => $volumePoints === 1,
                ],
            ];
        }

        $capturePercent = min(95.0, max(50.0, (float) $scope->capture_percent));
        $annualSavings = round($annualCost * ($capturePercent / 100), 2);
        $band = $this->complexityBand($complexityScore);
        $range = $this->feeRange($feeBands, $band, (string) $scope->delivery_mode, $scope);
        $quotedFee = $scope->quoted_fee !== null ? round((float) $scope->quoted_fee, 2) : $range['mid'];
        $paybackMonths = $annualSavings > 0 ? round($quotedFee / ($annualSavings / 12), 2) : null;
        $guessRatio = $confidenceCount > 0 ? round($guessCount / $confidenceCount, 4) : 0.0;

        $flags = [];
        if ($noApi) {
            $flags[] = $this->flag('no_api_on_key_system', 'high');
        }
        if ($paybackMonths !== null && $paybackMonths > 24) {
            $flags[] = $this->flag('payback_over_24_months', 'high');
        }
        if ($guessRatio >= 0.5) {
            $flags[] = $this->flag('low_confidence_scope', 'medium');
        }
        if ($quotedFee > 0 && $annualSavings > ($quotedFee * 5)) {
            $flags[] = $this->flag('savings_dwarf_quote', 'medium');
        }
        if (collect($tasks)->contains(fn (array $task): bool => (float) ($task['people_count'] ?? 0) === 1.0)) {
            $flags[] = $this->flag('single_person_dependency', 'low');
        }
        if ($scope->quoted_fee !== null && ((float) $scope->quoted_fee < $range['low'] || (float) $scope->quoted_fee > $range['high'])) {
            $flags[] = $this->flag('fee_override_outside_band', 'medium');
        }

        return [
            'task_rows' => $taskRows,
            'connection_rows' => $connectionRows,
            'annual_hours_wasted' => round($annualHours, 2),
            'annual_cost_wasted' => round($annualCost, 2),
            'capture_percent' => $capturePercent,
            'annual_savings' => $annualSavings,
            'complexity_score' => $complexityScore,
            'complexity_band' => $band,
            'quote_range' => $range,
            'quoted_fee' => $quotedFee,
            'payback_months' => $paybackMonths,
            'roi_ratio' => $quotedFee > 0 ? round(($annualSavings * max(1, (int) $scope->savings_horizon_years)) / $quotedFee, 4) : null,
            'guess_ratio' => $guessRatio,
            'flags' => $flags,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(mixed $rows): array
    {
        return collect(is_array($rows) ? $rows : [])
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->map(static fn (array $row): array => $row)
            ->values()
            ->all();
    }

    /** @param array<int, array<string, mixed>> $systems @return array<string, array<string, mixed>> */
    private function systemsByReference(array $systems): array
    {
        $references = [];
        foreach ($systems as $index => $system) {
            foreach ([(string) ($system['id'] ?? ''), (string) ($system['name'] ?? ''), (string) $index] as $reference) {
                if ($reference !== '') {
                    $references[$reference] = $system;
                }
            }
        }

        return $references;
    }

    /** @param array<string, array<string, mixed>> $systemsByReference @return array<string, mixed> */
    private function systemForReference(array $systemsByReference, mixed $reference): array
    {
        return is_scalar($reference) && isset($systemsByReference[(string) $reference])
            ? $systemsByReference[(string) $reference]
            : [];
    }

    /** @param array<string, mixed> $from @param array<string, mixed> $to */
    private function worstApiQuality(array $from, array $to): string
    {
        $qualities = [(string) ($from['api_quality'] ?? 'none'), (string) ($to['api_quality'] ?? 'none')];
        usort($qualities, fn (string $left, string $right): int => (self::API_POINTS[$right] ?? 5) <=> (self::API_POINTS[$left] ?? 5));

        return $qualities[0] ?? 'none';
    }

    /** @param array<string, mixed> $from @param array<string, mixed> $to */
    private function hasOauth(array $from, array $to): bool
    {
        return (string) ($from['auth'] ?? '') === 'oauth' || (string) ($to['auth'] ?? '') === 'oauth';
    }

    /** @param array<int, IntegrationFeeBand> $feeBands @return array{low:float,mid:float,high:float,currency:string} */
    private function feeRange(array $feeBands, string $band, string $deliveryMode, IntegrationScope $scope): array
    {
        $match = collect($feeBands)->first(fn (IntegrationFeeBand $feeBand): bool => $feeBand->complexity_band === $band
            && $feeBand->delivery_mode === $deliveryMode
            && $feeBand->is_active);

        if (! $match instanceof IntegrationFeeBand) {
            throw new InvalidArgumentException("No active {$band} fee band is configured for {$deliveryMode} delivery.");
        }

        $low = (float) $match->fee_low;
        $mid = (float) $match->fee_mid;
        $high = (float) $match->fee_high;

        if ($deliveryMode === IntegrationScope::DELIVERY_PARTNER && $scope->partner_cost_estimate !== null) {
            $margin = max(0.0, (float) ($scope->partner_margin_percent ?? 25)) / 100;
            $partnerFee = round(((float) $scope->partner_cost_estimate) * (1 + $margin), 2);
            $mid = max($mid, $partnerFee);
            $low = max($low, $partnerFee);
            $high = max($high, $partnerFee);
        }

        return ['low' => $low, 'mid' => $mid, 'high' => $high, 'currency' => $match->currency];
    }

    private function complexityBand(int $score): string
    {
        return match (true) {
            $score <= 6 => IntegrationFeeBand::BAND_S,
            $score <= 14 => IntegrationFeeBand::BAND_M,
            $score <= 26 => IntegrationFeeBand::BAND_L,
            default => IntegrationFeeBand::BAND_XL,
        };
    }

    /** @return array{code:string,severity:string,blocking:bool} */
    private function flag(string $code, string $severity): array
    {
        return ['code' => $code, 'severity' => $severity, 'blocking' => false];
    }

    private function nonNegativeNumber(mixed $value, string $field): float
    {
        if (! is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException("{$field} must be a non-negative number.");
        }

        return (float) $value;
    }
}
