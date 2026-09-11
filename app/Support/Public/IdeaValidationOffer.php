<?php

declare(strict_types=1);

namespace App\Support\Public;

use App\Models\ServiceRatePackage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves the public-facing Idea Validation offer (price) from the live
 * idea_validation Service Rate package.
 *
 * The price is never hardcoded: it is read from the current, active, effective
 * fixed-fee entrepreneur idea_validation package. If no such package exists, or
 * the lookup fails for any reason, this returns null - the page then shows
 * "Validate my idea" with no number and omits the Offer schema. A wrong price
 * is worse than no price, and the checkout re-reads the authoritative rate
 * before charging regardless.
 */
final class IdeaValidationOffer
{
    /**
     * @return array{price: float, currency: string, gst_rate: float, ex_gst_formatted: string}|null
     */
    public static function current(): ?array
    {
        $package = self::activePackage();

        if ($package === null) {
            return null;
        }

        $price = (float) $package->fixed_fee;

        if ($price <= 0) {
            return null;
        }

        $currency = $package->currency
            ?: (string) config('public_site.idea_validation.currency', 'NZD');
        $gstRate = (float) config('public_site.idea_validation.gst_rate', 0.15);

        return [
            'price' => $price,
            'currency' => $currency,
            'gst_rate' => $gstRate,
            'ex_gst_formatted' => self::formatMoney($price),
        ];
    }

    /**
     * The current active, effective, fixed-fee idea_validation package.
     *
     * Uses the model's own scope normalisation rather than a raw column match,
     * so it stays correct regardless of how the stored scope is spelled.
     */
    private static function activePackage(): ?ServiceRatePackage
    {
        try {
            $now = now();

            return ServiceRatePackage::query()
                ->where('service_type', ServiceRatePackage::SERVICE_ENTREPRENEUR)
                ->where('billing_model', ServiceRatePackage::BILLING_FIXED_FEE)
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $now))
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $now))
                ->orderByDesc('effective_from')
                ->get()
                ->first(function (ServiceRatePackage $package): bool {
                    return $package->packageScope() === ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION
                        && (float) $package->fixed_fee > 0;
                });
        } catch (Throwable $e) {
            // Never let a pricing lookup break a public page. No number is fine.
            Log::warning('Idea validation offer lookup failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Format a whole-dollar amount without decimals, otherwise to two places.
     */
    private static function formatMoney(float $amount): string
    {
        $decimals = fmod($amount, 1.0) === 0.0 ? 0 : 2;

        return '$'.number_format($amount, $decimals);
    }
}
