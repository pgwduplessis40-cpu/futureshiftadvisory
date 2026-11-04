<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final class FundingPresentation
{
    /** @param array<array-key,mixed> $decision */
    public static function headline(array $decision): string
    {
        $deficit = $decision['cash_deficit'] ?? null;
        $reserve = $decision['reserve_target_shortfall'] ?? null;
        $request = $decision['external_funding_request'] ?? null;
        $cash = ! is_numeric($deficit) ? 'The cash deficit cannot yet be verified.'
            : ((float) $deficit > 0 ? 'The forecast cash deficit is '.self::money($deficit).'.' : 'Forecast cash stays above zero.');

        return $cash.' Reserve-target shortfall: '.self::money($reserve).'. Proposed external request: '.self::money($request).'. These are separate amounts; a reserve shortfall does not establish a borrowing requirement.';
    }

    /**
     * @param  array<array-key,mixed>  $decision
     * @return list<array{label:string,amount:float|null,display_amount:string,note:string}>
     */
    public static function rows(array $decision): array
    {
        $definitions = [
            ['Planned one-off costs', 'launch_costs', 'Setup, replacement or growth costs entered in the budget.'],
            ['Operating cover', 'operating_cover_amount', ($decision['operating_cover_months'] ?? 0).' months of fixed costs under the reserve policy.'],
            ['Contingency reserve', 'contingency_amount', '10% reserve on planned one-off costs and operating cover.'],
            ['Reserve target', 'recommended_funding_target', 'A planning reserve target before comparing available cash.'],
            ['Funding and opening cash available', 'available_funding', 'Saved funding sources plus opening cash; provenance still requires review.'],
            ['Forecast cash deficit', 'cash_deficit', 'The maximum forecast cash deficit, separate from the reserve target.'],
            ['Reserve-target shortfall', 'reserve_target_shortfall', 'A policy review amount; not automatically an external funding request.'],
            ['Proposed external request', 'external_funding_request', 'An independently recorded request; not inferred from either shortfall.'],
        ];

        return array_map(fn (array $row): array => ['label' => $row[0], 'amount' => is_numeric($decision[$row[1]] ?? null) ? (float) $decision[$row[1]] : null,
            'display_amount' => self::money($decision[$row[1]] ?? null), 'note' => $row[2]], $definitions);
    }

    private static function money(mixed $value): string
    {
        return is_numeric($value) ? '$'.number_format((float) $value, 2) : 'Not recorded';
    }
}
