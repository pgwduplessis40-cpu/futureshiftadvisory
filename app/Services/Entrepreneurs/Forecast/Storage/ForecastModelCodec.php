<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast\Storage;

use App\Services\Entrepreneurs\Forecast\ReservePolicy;

/**
 * Reads a stored reconstruction back into engine value objects.
 *
 * Decoding is deliberately strict. A payload that has lost a required field,
 * or gained one of the wrong type, raises rather than loading a thinner model
 * than the advisor saved, because a silently emptied driver reads downstream
 * as a confident zero.
 */
final class ForecastModelCodec
{
    public static function decode(string $engineVersion, mixed $drivers, mixed $actuals, mixed $reservePolicy): ForecastModelInputs
    {
        ForecastEngineVersion::assertReadable($engineVersion);

        return new ForecastModelInputs(
            DriverCodec::calendar(StoredValues::child($drivers, 'calendar')),
            DriverCodec::streams($drivers, 'streams'),
            DriverCodec::founderMonths($drivers, 'founder_months'),
            StoredValues::has($drivers, 'cash_opening') ? DriverCodec::cashOpening(StoredValues::child($drivers, 'cash_opening')) : null,
            StoredValues::has($drivers, 'bank_opening') ? DriverCodec::bankOpening(StoredValues::child($drivers, 'bank_opening')) : null,
            DriverCodec::tradeItems($drivers, 'trade_items'),
            DriverCodec::ownerPaySteps($drivers, 'owner_pay_steps'),
            DriverCodec::assets($drivers, 'assets'),
            StoredValues::has($drivers, 'tax_policy') ? DriverCodec::taxPolicy(StoredValues::child($drivers, 'tax_policy')) : null,
            DriverCodec::taxPeriods($drivers, 'tax_periods'),
            ActualsCodec::months($actuals, 'months'),
            $reservePolicy === null ? null : self::reservePolicy($reservePolicy),
            // Reconstructions saved before these were captured load with them
            // absent rather than failing to load at all. An older model is
            // incomplete, which the run reports; it is not unreadable.
            StoredValues::text($drivers, 'owner_pay_source'),
            StoredValues::text($drivers, 'owner_pay_first_payment_on'),
            StoredValues::text($drivers, 'opening_eligible_losses'),
            DriverCodec::fundingSources($drivers, 'funding_sources'),
            StoredValues::flag($drivers, 'funding_inventory_complete'),
        );
    }

    /**
     * Only the policy's declared inputs are read. Its base reserve,
     * contingency and target are serialized for readers but recomputed on
     * load, so a stored target cannot drift away from the policy behind it.
     */
    public static function reservePolicy(mixed $node): ReservePolicy
    {
        return new ReservePolicy(
            StoredValues::text($node, 'monthly_fixed_costs'),
            StoredValues::requiredCount($node, 'months_covered'),
            StoredValues::flag($node, 'includes_owner_pay'),
            StoredValues::text($node, 'one_off_cash_costs'),
            StoredValues::requiredText($node, 'contingency_rate'),
            StoredValues::text($node, 'basis'),
        );
    }
}
