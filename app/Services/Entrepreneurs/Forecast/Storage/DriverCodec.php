<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast\Storage;

use App\Services\Entrepreneurs\Forecast\AssetInput;
use App\Services\Entrepreneurs\Forecast\BankOpening;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\FixedCost;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\FundingSource;
use App\Services\Entrepreneurs\Forecast\OwnerPayStep;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Services\Entrepreneurs\Forecast\TaxCashPlan;
use App\Services\Entrepreneurs\Forecast\TaxPeriod;
use App\Services\Entrepreneurs\Forecast\TaxPolicy;
use App\Services\Entrepreneurs\Forecast\TradeItem;

/**
 * Rebuilds driver inputs from stored JSON.
 *
 * Decoding goes through the engine's own constructors, so a stored payload
 * cannot load into a shape the engine would have refused to accept. A value
 * that was invalid on the way in cannot become valid by having been written to
 * the database in between.
 */
final class DriverCodec
{
    public static function calendar(mixed $node): ForecastCalendar
    {
        return new ForecastCalendar(
            StoredValues::requiredText($node, 'start_month'),
            StoredValues::requiredCount($node, 'months'),
        );
    }

    /** @return list<RevenueStream> */
    public static function streams(mixed $node, string $key): array
    {
        $streams = [];
        foreach (StoredValues::items($node, $key) as $item) {
            $streams[] = self::stream($item);
        }

        return $streams;
    }

    public static function stream(mixed $node): RevenueStream
    {
        $schedule = [];
        foreach (StoredValues::keys($node, 'schedule') as $period) {
            $schedule[$period] = self::streamMonth(StoredValues::child(StoredValues::child($node, 'schedule'), $period));
        }

        return new RevenueStream(
            StoredValues::requiredText($node, 'id'),
            StoredValues::requiredText($node, 'name'),
            StoredValues::text($node, 'unit'),
            StoredValues::text($node, 'price'),
            StoredValues::text($node, 'preparation_hours_per_unit'),
            StoredValues::text($node, 'delivery_hours_per_unit'),
            StoredValues::flag($node, 'in_base_case'),
            StoredValues::text($node, 'evidence_status'),
            $schedule,
        );
    }

    public static function streamMonth(mixed $node): StreamMonth
    {
        return new StreamMonth(
            StoredValues::text($node, 'booked_units'),
            StoredValues::text($node, 'delivery_units'),
            StoredValues::text($node, 'invoiced_units'),
            StoredValues::text($node, 'founder_hours'),
            StoredValues::text($node, 'additional_capacity_units'),
            StoredValues::text($node, 'provider'),
            StoredValues::text($node, 'provider_unit_cost'),
            StoredValues::text($node, 'no_cost_basis'),
        );
    }

    /** @return array<string,FounderMonth> */
    public static function founderMonths(mixed $node, string $key): array
    {
        $months = [];
        foreach (StoredValues::keys($node, $key) as $period) {
            $member = StoredValues::child(StoredValues::child($node, $key), $period);
            $months[$period] = new FounderMonth(
                StoredValues::text($member, 'available_hours'),
                StoredValues::text($member, 'administration_hours'),
                StoredValues::text($member, 'business_development_hours'),
            );
        }

        return $months;
    }

    public static function cashOpening(mixed $node): CashOpening
    {
        return new CashOpening(
            StoredValues::text($node, 'amount'),
            StoredValues::text($node, 'source'),
            StoredValues::flag($node, 'verified'),
        );
    }

    public static function bankOpening(mixed $node): BankOpening
    {
        return new BankOpening(
            StoredValues::text($node, 'analytical'),
            StoredValues::text($node, 'gst_bridge'),
            StoredValues::text($node, 'bank'),
            StoredValues::text($node, 'restricted'),
            StoredValues::text($node, 'source'),
            StoredValues::flag($node, 'reviewed'),
        );
    }

    /** @return list<TradeItem> */
    public static function tradeItems(mixed $node, string $key): array
    {
        $items = [];
        foreach (StoredValues::items($node, $key) as $item) {
            $items[] = self::tradeItem($item);
        }

        return $items;
    }

    public static function tradeItem(mixed $node): TradeItem
    {
        return new TradeItem(
            StoredValues::requiredText($node, 'id'),
            StoredValues::requiredText($node, 'source'),
            StoredValues::requiredText($node, 'kind'),
            StoredValues::text($node, 'raised_on'),
            StoredValues::text($node, 'amount'),
            StoredValues::has($node, 'settlements') ? self::movements($node, 'settlements') : null,
        );
    }

    /** @return list<CashMovement> */
    public static function movements(mixed $node, string $key): array
    {
        $movements = [];
        foreach (StoredValues::items($node, $key) as $item) {
            $movements[] = new CashMovement(
                StoredValues::requiredText($item, 'id'),
                StoredValues::requiredText($item, 'source'),
                StoredValues::text($item, 'date'),
                StoredValues::text($item, 'amount'),
                StoredValues::requiredText($item, 'direction'),
                StoredValues::requiredText($item, 'category'),
                StoredValues::requiredCount($item, 'sequence'),
                StoredValues::flag($item, 'verified'),
            );
        }

        return $movements;
    }

    /** @return list<FundingSource> */
    public static function fundingSources(mixed $node, string $key): array
    {
        $sources = [];
        foreach (StoredValues::items($node, $key) as $item) {
            $sources[] = new FundingSource(
                StoredValues::requiredText($item, 'id'),
                StoredValues::requiredText($item, 'source'),
                StoredValues::text($item, 'identity'),
                StoredValues::text($item, 'type'),
                StoredValues::text($item, 'amount'),
                StoredValues::text($item, 'commitment'),
                StoredValues::text($item, 'available_from'),
                StoredValues::text($item, 'drawn_on'),
                StoredValues::text($item, 'restriction'),
                StoredValues::flag($item, 'verified'),
            );
        }

        return $sources;
    }

    /** @return list<FixedCost> */
    public static function fixedCosts(mixed $node, string $key): array
    {
        $costs = [];
        foreach (StoredValues::items($node, $key) as $item) {
            $costs[] = new FixedCost(
                StoredValues::requiredText($item, 'id'),
                StoredValues::requiredText($item, 'source'),
                StoredValues::text($item, 'rate'),
                StoredValues::text($item, 'quantity'),
                StoredValues::text($item, 'frequency'),
                StoredValues::text($item, 'first_invoice_on'),
                StoredValues::text($item, 'last_invoice_on'),
                StoredValues::count($item, 'creditor_days'),
                StoredValues::flag($item, 'reviewed'),
            );
        }

        return $costs;
    }

    /** @return list<OwnerPayStep> */
    public static function ownerPaySteps(mixed $node, string $key): array
    {
        $steps = [];
        foreach (StoredValues::items($node, $key) as $item) {
            $steps[] = new OwnerPayStep(
                StoredValues::requiredText($item, 'effective_on'),
                StoredValues::text($item, 'weekly_amount'),
                StoredValues::requiredText($item, 'trigger'),
                StoredValues::text($item, 'threshold'),
            );
        }

        return $steps;
    }

    /** @return list<AssetInput> */
    public static function assets(mixed $node, string $key): array
    {
        $assets = [];
        foreach (StoredValues::items($node, $key) as $item) {
            $assets[] = new AssetInput(
                StoredValues::requiredText($item, 'id'),
                StoredValues::requiredText($item, 'source'),
                StoredValues::text($item, 'cost'),
                StoredValues::text($item, 'classification'),
                StoredValues::text($item, 'policy_source'),
                StoredValues::flag($item, 'reviewed'),
                StoredValues::text($item, 'paid_on'),
                StoredValues::text($item, 'recognition_start_on'),
                StoredValues::count($item, 'life_months'),
                StoredValues::text($item, 'residual'),
                StoredValues::text($item, 'method'),
                StoredValues::text($item, 'opening_depreciation'),
            );
        }

        return $assets;
    }

    public static function taxCashPlan(mixed $node): TaxCashPlan
    {
        return new TaxCashPlan(
            StoredValues::text($node, 'tax_state'),
            StoredValues::text($node, 'gst_state'),
            StoredValues::text($node, 'tax_source'),
            StoredValues::text($node, 'gst_source'),
            StoredValues::has($node, 'movements') ? self::movements($node, 'movements') : [],
        );
    }

    public static function taxPolicy(mixed $node): TaxPolicy
    {
        return new TaxPolicy(
            StoredValues::text($node, 'entity_basis'),
            StoredValues::text($node, 'rate_percent'),
            StoredValues::flag($node, 'losses_eligible'),
            StoredValues::text($node, 'policy_source'),
            StoredValues::text($node, 'eligibility_source'),
            StoredValues::text($node, 'version'),
        );
    }

    /** @return list<TaxPeriod> */
    public static function taxPeriods(mixed $node, string $key): array
    {
        $periods = [];
        foreach (StoredValues::items($node, $key) as $item) {
            $periods[] = new TaxPeriod(
                StoredValues::requiredText($item, 'start_on'),
                StoredValues::requiredText($item, 'end_on'),
                StoredValues::text($item, 'accounting_profit'),
                StoredValues::text($item, 'tax_adjustments'),
            );
        }

        return $periods;
    }
}
