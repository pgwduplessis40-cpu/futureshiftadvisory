<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\ServiceRatePackage;

/**
 * Reads the currently sellable entrepreneur packages from Service Rates.
 *
 * Public pages deliberately receive no cached or fallback dollar value: a
 * missing rate must remove the price rather than advertise the wrong one.
 */
final class EntrepreneurServiceOffer
{
    /** @return array{available:bool,label:string,amount_ex_gst:float|null,currency:string|null} */
    public function forScope(string $scope): array
    {
        $now = now();
        $package = ServiceRatePackage::query()
            ->where('service_type', ServiceRatePackage::SERVICE_ENTREPRENEUR)
            ->where('package_scope', $scope)
            ->where('billing_model', ServiceRatePackage::BILLING_FIXED_FEE)
            ->where('is_active', true)
            ->where('effective_from', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $now);
            })
            ->whereNotNull('fixed_fee')
            ->where('fixed_fee', '>', 0)
            ->latest('effective_from')
            ->first();

        if (! $package instanceof ServiceRatePackage) {
            return [
                'available' => false,
                'label' => 'Validate my idea',
                'amount_ex_gst' => null,
                'currency' => null,
            ];
        }

        return [
            'available' => true,
            'label' => $package->client_label ?: ServiceRatePackage::packageScopeLabel($scope),
            'amount_ex_gst' => (float) $package->fixed_fee,
            'currency' => $package->currency ?: 'NZD',
        ];
    }
}
