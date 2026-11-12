<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Support\Methodology\ProvidesMethodology;

/**
 * The dated, classified and sourced cash entries behind a month's movement.
 *
 * MI-01 reconciles the base case's entries to its reported movement and SS-01
 * reconciles each scenario's to its own, against the same arithmetic and the
 * same classification. One emitter serves both: a scenario classified by a
 * second implementation could reconcile against a taxonomy the base case does
 * not use, and the two would drift silently because each would still add up.
 */
final class LedgerEntryFacts implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.dated_cash'];
    }

    /**
     * @param  list<CashMovement>  $movements
     * @param  list<SourceReference>  $at
     * @return list<Fact>
     */
    public function capture(ForecastCalendar $calendar, ForecastModelInputs $inputs, array $movements,
        array $at, string $scenario = 'base'): array
    {
        $offsets = array_flip($calendar->periods);
        // Which declared source each financing inflow came from. The rule
        // requires every one of them to map to a named source, and the map is
        // built from the inputs rather than guessed from the movement, so a
        // source nobody has named leaves its draw unmapped and reported.
        $funders = [];
        foreach ($inputs->fundingSources as $funding) {
            $funders[$funding->id.'.draw'] = $funding->identity;
        }

        $facts = [];
        foreach ($movements as $movement) {
            $period = $movement->date === null ? null : substr($movement->date, 0, 7);
            if ($period === null || ! isset($offsets[$period])) {
                // A movement the ledger could not date belongs to no month,
                // and one dated past the calendar is not part of the curve
                // being checked. Filing either under a month would put it in
                // a reconciliation it is not in. Nothing is hidden by that:
                // an undated movement already withholds every balance in the
                // ledger, so the months report themselves as unverifiable.
                continue;
            }
            $label = 'month:'.($offsets[$period] + 1);
            $facts[] = new Fact('ledger.amount', $movement->amount, $movement->id, $label, $scenario,
                unit: 'NZD', confirmed: true, sources: $at);
            $facts[] = new Fact('ledger.date', $movement->date, $movement->id, $label, $scenario,
                confirmed: true, sources: $at);
            // The record and the movement within it. One invoice can settle in
            // instalments, and the rule requires each ledger line to be
            // identifiable once; the record on its own would read as the same
            // line counted twice.
            $facts[] = new Fact('ledger.source', $movement->source.' ('.$movement->id.')', $movement->id,
                $label, $scenario, confirmed: true, sources: $at);
            $category = $this->category($movement);
            if ($category !== null) {
                $facts[] = new Fact('ledger.category', $category, $movement->id, $label, $scenario,
                    confirmed: true, sources: $at);
            }
            if (($funders[$movement->id] ?? null) !== null) {
                $facts[] = new Fact('ledger.funding_identity', $funders[$movement->id], $movement->id,
                    $label, $scenario, confirmed: true, sources: $at);
            }
        }

        return $facts;
    }

    /**
     * The gate's ledger category for an engine movement, or none.
     *
     * The two vocabularies do not line up, and the direction decides as much
     * as the category does. The rule holds amounts non-negative and takes the
     * direction from the classification, so every inflow has to reach an
     * inflow category and every outflow an outflow one, or the classified
     * lines would not sum to the month's reported movement.
     *
     * Interest, fees and owner pay are operating payments: the rule has no
     * category for any of them, a distribution is separately named, and cash
     * paid out of the business for the owner's work is an operating payment
     * rather than a return of capital.
     *
     * Four combinations have no honest home and get no category, which makes
     * the rule report the entry rather than reconcile it. A tax or GST refund
     * and an asset disposal are receipts, but the rule's three inflow
     * categories are customer receipts, other operating receipts and financing
     * inflows, and none of them describes a refund of tax or the proceeds of a
     * disposal without asserting something about its nature. A restricted
     * transfer is a movement between restricted and unrestricted cash, which
     * the rule's categories do not cover at all. Filing any of them under a
     * category that does not fit would let a month reconcile on a
     * classification the gate's own taxonomy cannot support; reporting it
     * unclassified is the honest outcome and names the entry to the advisor.
     */
    private function category(CashMovement $movement): ?string
    {
        return match ($movement->direction) {
            'in' => match ($movement->category) {
                'operating_receipt' => 'customer_receipts',
                'funding' => 'financing_inflows',
                default => null,
            },
            'out' => match ($movement->category) {
                'operating_payment', 'owner_pay', 'interest', 'fees' => 'operating_payments',
                'tax' => 'cash_tax',
                'gst' => 'gst',
                'capex' => 'capex',
                'debt_principal' => 'principal',
                'distribution' => 'distributions',
                default => null,
            },
            default => null,
        };
    }
}
