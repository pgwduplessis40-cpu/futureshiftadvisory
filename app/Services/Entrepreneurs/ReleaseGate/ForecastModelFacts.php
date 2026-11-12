<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\ForecastRun;
use App\Services\Entrepreneurs\Forecast\MonthlyTaxChain;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelCodec;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Produces release-gate facts from a selected version-2 reconstruction.
 *
 * Everything here is evidenced against the captured snapshot rather than
 * against live state, so a gate run and a later re-reading of it see the same
 * model. The reconstruction is decoded back through the engine's own codec,
 * which means a payload the engine would refuse produces no facts at all
 * rather than a thinner set that reads as a complete model with gaps.
 *
 * Periods follow the `month:N` convention the rest of the gate already uses,
 * counted from the model's own start month, so a fact produced here can be
 * compared with one produced from the stored budget.
 *
 * @phpstan-import-type CashResult from ForecastRun
 */
final class ForecastModelFacts implements ProvidesMethodology
{
    /**
     * Mostly this class adapts engine output into sourced facts, as the
     * budget and plan producers beside it do. It is marked rather than
     * excluded because it does derive figures of its own, and a formula that
     * decides a release finding has to name the methodology it belongs to:
     * the month-on-month ramp per stream is a revenue driver, and the
     * month's net movement and the annual cash totals belong to dated cash.
     *
     * @return list<string>
     */
    public static function methodologyIds(): array
    {
        return ['entrepreneur.driver_revenue', 'entrepreneur.dated_cash'];
    }

    /**
     * Keys this producer deliberately does not emit.
     *
     * Each is read by a rule that is already enabled, and a competing record
     * resolves to nothing rather than to the first of them, so emitting one
     * here would turn a figure a rule can read into a figure it reports as
     * missing. `stream.revenue` is read by PM-04, which currently sees none;
     * `scope.required_horizon` feeds MI-06 and the snapshot's own horizon; and
     * a second `model.external_request` would make PM-12's lookup ambiguous.
     *
     * `scenario.lowest_cash` used to be withheld here because nothing could
     * state it: SS-01 compares it against the minimum of the month-end closing
     * balances, while the ledger's own lowest cash is the balance after each
     * individual movement and can dip below every month end, so publishing the
     * ledger's figure would have misstated the measure. The scenario run now
     * computes the month-end trough from each scenario's own curve, which is
     * the measure the rule asks for, so the key is emitted rather than
     * withheld. It stays in `SUPERSEDES` below: the budget's record is taken
     * off the old cash curve and still has to step aside.
     *
     * The two display keys are withheld for a different reason again: the
     * budget already states both of them, from two genuinely separate places
     * — a trace summed over its own cost rows, and the monthly fixed cost its
     * computation reports — which is what makes the rule's whole-dollar
     * comparison of them worth anything. A third record under either key
     * would resolve both to nothing and take a working check with it, and a
     * reconstruction publishing one figure under both would manufacture a
     * check that passes because it was handed the same number twice.
     *
     * @var list<string>
     */
    public const WITHHELD = ['stream.revenue', 'scope.required_horizon', 'model.external_request',
        'cost.trace_display', 'model.fixed_cost_display'];

    /**
     * Keys a selected reconstruction takes over from the budget.
     *
     * A selected reconstruction is the plan financial model, so where it and
     * the stored budget both describe the dated cash position, the budget's
     * record steps aside rather than competing: two records under one key
     * resolve to nothing, which would turn a figure a rule can read into one
     * it reports as missing.
     *
     * The takeover covers every scenario, not only the base case, and that
     * costs something worth naming. A budget scenario's cash curve is built
     * off the base curve the reconstruction replaces, and the cash-ledger
     * rule checks each scenario's first month against the model's own opening
     * cash, which is a single figure with no scenario of its own. Leaving the
     * scenario curves in place beside a reconstruction therefore has every
     * one of them start from a balance the plan's financial model does not
     * use, which the rule demonstrates as a mismatch. So SS-01 loses the
     * scenarios it reconciles today and reports them as not established: the
     * reconstruction states no scenarios, and a check that passes against the
     * model a plan has stopped using is worth less than an honest gap.
     *
     * The funding keys are here for the same reason the cash ones are. The
     * reconstruction now holds its own funding inventory, so the budget's
     * named sources and declared total belong to the model it replaced, and
     * a rule iterating both would walk two models' funding at once. Where
     * the reconstruction has no inventory, or has not had one declared
     * complete, the figures go unstated rather than being borrowed back from
     * the budget — an unestablished funding position reported as such.
     *
     * One key is taken over without being replaced, and it is in `WITHHELD`
     * above. Leaving the budget's record would be worse than leaving the
     * figure unstated, because a rule would then reconcile a figure taken off
     * the old cash curve against the new one. `model.cash_deficit` and
     * `model.reserve_gap` come off that same old curve but are left alone:
     * PM-12 tests them against the plan's own funding claim rather than
     * against any curve, so they still stand on their own terms.
     *
     * @var list<string>
     */
    public const SUPERSEDES = ['model.opening_cash', 'model.available_funds', 'model.funding_total', 'funding.amount',
        'cash.opening', 'cash.movement', 'cash.closing', 'cash.annual_movement', 'cash.annual_closing', 'scenario.lowest_cash'];

    public function __construct(
        private readonly ForecastRun $run = new ForecastRun,
        private readonly ScenarioFacts $scenarios = new ScenarioFacts,
        private readonly CashCurveFacts $curve = new CashCurveFacts,
        private readonly TaxPolicyFacts $taxPolicy = new TaxPolicyFacts,
    ) {}

    /** @return list<Fact> */
    public function capture(SourceRecords $source): array
    {
        if (! is_array($source->value('forecast_model'))) {
            return [];
        }
        try {
            $inputs = ForecastModelCodec::decode(
                (string) $source->value('forecast_model.engine_version'),
                $source->value('forecast_model.drivers'),
                $source->value('forecast_model.actuals'),
                $source->value('forecast_model.reserve_policy'),
            );
        } catch (\InvalidArgumentException) {
            // A reconstruction the engine cannot read is evidence of nothing.
            // The advisor screen reports the load failure; inventing partial
            // facts from it here would put unreadable figures in front of a
            // rule as though they had been verified.
            return [];
        }

        // Run the dated cash side once. Both the handover facts and the ledger
        // facts read it, and the run is expensive enough that doing it twice
        // would be a second answer to the same question.
        $cash = $this->run->cash($inputs);

        return [...$this->model($source, $inputs), ...$this->streams($source, $inputs),
            ...$this->actuals($source, $inputs), ...$this->cash($source, $inputs, $cash),
            ...$this->ledger($source, $inputs, $cash), ...$this->funding($source, $inputs),
            ...$this->costs($source, $inputs), ...$this->tax($source, $inputs, $cash),
            // The stress dimensions live in their own producer. They are the
            // scenario engines' evidence rather than this run's, and they are
            // measured by shocking the reconstruction and reading the result
            // back, which is a different job from reporting what this run
            // computed once.
            ...$this->scenarios->capture($source, $inputs)];
    }

    /**
     * The tax policy, and the monthly chain of profit and carried losses.
     *
     * The rule wants a monthly chain, and until now nothing stated one. The
     * stored tax periods hold a period's profit, which is the form a return
     * is filed in; splitting one into twelve months would invent eleven of
     * them, so the monthly profit comes from the only place on the platform
     * where it is a recorded figure rather than a derived one — the profit and
     * loss of a reviewed actual month, read from the accounts.
     *
     * That bounds what this can state. Months nobody has reviewed carry no
     * profit here, so the chain stops where the reviewed months stop and the
     * rule reports the horizon as uncovered, which is the truthful reading: a
     * forecast month's profit is not recorded anywhere, and deriving one would
     * mean deciding whether a receivable is the same revenue a stream already
     * counted. That decision is open, and a tax figure is a poor place to
     * take it quietly.
     *
     * @param  CashResult  $cash
     * @return list<Fact>
     */
    private function tax(SourceRecords $source, ForecastModelInputs $inputs, array $cash): array
    {
        $policy = $inputs->taxPolicy;
        if ($policy === null) {
            return [];
        }
        $at = [$source->reference('forecast_model.drivers.tax_policy')];
        // The arrangement itself comes from the shared emitter, because SS-01
        // holds each scenario's against this one and the two sides have to be
        // derived the same way for the comparison to mean anything.
        $facts = $this->taxPolicy->capture($source, $inputs);
        $reviewed = [];
        foreach ($inputs->actualMonths as $index => $month) {
            // A month nobody reconciled is not a recorded profit. Reading one
            // off an unreviewed month would put an unchecked figure at the
            // head of a chain every later month depends on.
            if ($month->source->reconciled === true && $month->profit->profitBeforeTax !== null) {
                $reviewed[$month->period] = [$month->profit->profitBeforeTax, $index];
            }
        }
        // A reviewed month's accounts outrank the decomposition for the same
        // month. The decomposition is what the model implies; the accounts are
        // what happened, and where both exist the second is the better figure.
        $profits = [];
        foreach ($this->run->profit($inputs, $cash['movements'])['months'] as $month) {
            if ($month['profit'] !== null) {
                $profits[$month['period']] = $month['profit'];
            }
        }
        foreach ($reviewed as $period => $row) {
            $profits[$period] = $row[0];
        }
        $adjustments = $this->adjustments($inputs);
        $chain = (new MonthlyTaxChain)->calculate(
            $inputs->calendar->periods,
            $profits,
            $adjustments,
            $policy->ratePercent,
            $inputs->openingEligibleLosses,
            $policy->lossesEligible,
        );
        foreach ($chain['months'] as $offset => $month) {
            $period = $month['period'];
            if ($month['profit'] === null) {
                continue;
            }
            $label = 'month:'.($offset + 1);
            // The basis says which of the two a month's profit is, because a
            // figure read from reviewed accounts and one implied by the model
            // carry different weight and a reader has to be able to tell.
            $where = isset($reviewed[$period])
                ? [$source->reference('forecast_model.actuals.months.'.$reviewed[$period][1])]
                : [$source->reference('forecast_model.drivers')];
            $facts[] = new Fact('tax.profit', $month['profit'], period: $label, unit: 'NZD',
                basis: isset($reviewed[$period]) ? 'reviewed_actual_profit_before_tax' : 'recognised_profit_before_tax',
                confirmed: isset($reviewed[$period]), sources: $where);
            $facts[] = new Fact('tax.adjustment', $month['adjustment'], period: $label, unit: 'NZD',
                confirmed: true, sources: [$source->reference('forecast_model.drivers.tax_periods')]);
            // Withheld together. Expense without the closing balance behind it
            // would let a month reconcile while the losses it consumed went
            // unstated, which is the half of the chain that carries forward.
            if ($month['expense'] !== null && $month['loss_closing'] !== null) {
                $facts[] = new Fact('tax.expense', $month['expense'], period: $label, unit: 'NZD', confirmed: true, sources: [...$at, ...$where]);
                $facts[] = new Fact('tax.closing_losses', $month['loss_closing'], period: $label, unit: 'NZD', confirmed: true, sources: [...$at, ...$where]);
                $facts = [...$facts, ...$this->taxCash($source, $inputs, $period, $label, $month['expense'])];
            }
        }

        return $facts;
    }

    /**
     * The tax cash scheduled for a month, and the payable it moves.
     *
     * The schedule is a separate statement from the expense: a month's tax
     * can be recognised long before it is paid, and the gap between the two
     * is the tax payable the business carries. Deriving the payment from the
     * expense would state a payment date nobody scheduled, so the payment is
     * read from the schedule and only the payable movement is derived.
     *
     * The payment is the cash the schedule sends out. A month that also
     * carries a refund states no payment at all rather than a net figure: the
     * ledger gives a refund no category, because the rule's inflow categories
     * do not describe one, so a net figure here could never agree with the
     * ledger it is checked against. Reporting the month unestablished names
     * that honestly instead.
     *
     * @return list<Fact>
     */
    private function taxCash(SourceRecords $source, ForecastModelInputs $inputs, string $period, string $label, string $expense): array
    {
        $plan = $inputs->taxCashPlan;
        // A plan whose scope nobody has stated says nothing about any month.
        // An empty schedule is equally a business with no tax due and one
        // nobody has worked out, and only the scope state tells them apart.
        if ($plan?->taxState === null) {
            return [];
        }
        $at = [$source->reference('forecast_model.drivers.tax_cash_plan')];
        $paid = BigDecimal::zero();
        $on = null;
        foreach ($plan->movements as $movement) {
            if ($movement->category !== 'tax' || $movement->date === null || substr($movement->date, 0, 7) !== $period) {
                continue;
            }
            if ($movement->direction === 'in' || $movement->amount === null) {
                return [];
            }
            $paid = $paid->plus($movement->amount);
            // The month's own payment date, taken as the last one in it. The
            // rule requires a real date on the payment, and a month settled in
            // instalments is settled when the last of them lands.
            $on = $on === null || $movement->date > $on ? $movement->date : $on;
        }
        // A month with nothing scheduled paid nothing, which is a position
        // rather than a gap: the expense it recognised is carried as tax
        // payable until a later month settles it. A nil payment has no date
        // of its own, so it is dated to the month it belongs to.
        $on ??= $period.'-01';

        // Both at cents, because they are currency and are read beside the
        // expense they reconcile to. A sum of amounts typed without decimals
        // would otherwise state a payment of "700" against an expense of
        // "700.00", which is the same figure written two ways.
        return [
            new Fact('tax.payment', Checks::money($paid), period: $label, unit: 'NZD', asOf: $on, confirmed: true, sources: $at),
            new Fact('tax.payable_movement', Checks::money(BigDecimal::of($expense)->minus($paid)), period: $label,
                unit: 'NZD', confirmed: true, sources: $at),
        ];
    }

    /**
     * Each calendar month's tax adjustments, from the planning periods.
     *
     * An adjustment is determined when the return is prepared, so a period's
     * adjustments are recognised in the month that period ends and the months
     * before it carry none. That places the timing, not the amounts: the
     * figure is the advisor's and the period total is exact either way. A
     * month inside no declared period carries no adjustment at all, because
     * nobody has stated one for it.
     *
     * @return array<string,string|null>
     */
    private function adjustments(ForecastModelInputs $inputs): array
    {
        $adjustments = [];
        foreach ($inputs->calendar->periods as $period) {
            foreach ($inputs->taxPeriods as $tax) {
                $opens = substr($tax->startOn, 0, 7);
                $closes = substr($tax->endOn, 0, 7);
                if ($period < $opens || $period > $closes) {
                    continue;
                }
                $adjustments[$period] = $period === $closes ? ($tax->taxAdjustments ?? '0.00') : '0.00';
                break;
            }
        }

        return $adjustments;
    }

    /**
     * The recurring overheads, what each recognises monthly, and the cash
     * behind them.
     *
     * This is evidence MI-03 has never had. Trade items are individual dated
     * payables rather than rate-times-cadence arrangements, so nothing
     * produced a fixed-cost rate, quantity, frequency or expense and the rule
     * reported its whole schedule missing however many payables were recorded.
     *
     * The figures here are unrounded, which is what the rule asks for in so
     * many words: its missing-schedule message names "unrounded" rates,
     * quantities, frequencies and monthly schedules. An exact allocation of
     * cents cannot be straight-line, and the rounded totals a reader sees are
     * published separately as the display facts the rule compares at
     * whole-dollar precision.
     *
     * @return list<Fact>
     */
    private function costs(SourceRecords $source, ForecastModelInputs $inputs): array
    {
        if ($inputs->fixedCosts === [] && $inputs->fixedCostScheduleComplete === null) {
            return [];
        }
        $run = $this->run->fixedCosts($inputs);
        $facts = [];
        $complete = $inputs->fixedCostScheduleComplete;
        if ($complete !== null) {
            $facts[] = new Fact('cost.schedule_complete', $complete, confirmed: true,
                sources: [$source->reference('forecast_model.drivers.fixed_cost_schedule_complete')]);
        }
        $offsets = array_flip($inputs->calendar->periods);
        foreach ($run['lines'] as $index => $line) {
            $at = [$source->reference('forecast_model.drivers.fixed_costs.'.$index)];
            foreach ($line['months'] as $month) {
                if (! isset($offsets[$month['period']])) {
                    continue;
                }
                $label = 'month:'.($offsets[$month['period']] + 1);
                // Rate, quantity and cadence are restated against every month
                // rather than once against the cost, because the rule reads
                // them parallel to the month's expense. A cost stated once and
                // an expense stated monthly would leave the rule unable to say
                // which month's expense a rate belongs to.
                $facts[] = new Fact('cost.rate', $line['rate'], $line['id'], $label, unit: 'NZD', confirmed: true, sources: $at);
                $facts[] = new Fact('cost.quantity', $line['quantity'], $line['id'], $label, unit: 'units', confirmed: true, sources: $at);
                $facts[] = new Fact('cost.frequency', $line['frequency'], $line['id'], $label, confirmed: true, sources: $at);
                $facts[] = new Fact('cost.expense', $month['expense'], $line['id'], $label, unit: 'NZD',
                    basis: 'straight_line_annualised', confirmed: $line['reviewed'] === true, sources: $at);
                $facts[] = new Fact('cost.accrual_movement', $month['accrual_movement'], $line['id'], $label, unit: 'NZD',
                    confirmed: true, sources: $at);
                foreach ($month['payments'] as $payment) {
                    $facts[] = new Fact('cost.payment', $payment->amount, $line['id'], $label, unit: 'NZD',
                        asOf: $payment->date, confirmed: true, sources: $at);
                }
            }
        }
        $at = [$source->reference('forecast_model.drivers.fixed_costs')];
        $years = [];
        foreach ($run['totals'] as $period => $total) {
            if (! isset($offsets[$period])) {
                continue;
            }
            $number = $offsets[$period] + 1;
            $facts[] = new Fact('model.fixed_cost', $total, period: 'month:'.$number, unit: 'NZD', confirmed: true, sources: $at);
            $years[(int) ceil($number / 12)][$number] = $total;
        }
        foreach ($years as $year => $rows) {
            // A year the calendar does not finish has no annual total, and a
            // sum of the months it does hold would be an annual figure for
            // part of a year. One unpriced cost withholds the whole year.
            if (count($rows) !== 12 || in_array(null, $rows, true)) {
                continue;
            }
            $sum = BigDecimal::zero();
            foreach ($rows as $row) {
                $sum = $sum->plus((string) $row);
            }
            $facts[] = new Fact('cost.annual_total', (string) $sum, period: 'year:'.$year, unit: 'NZD', confirmed: true, sources: $at);
        }

        return $facts;
    }

    /**
     * The named funding behind the forecast, and what it adds up to.
     *
     * A total is published only once the advisor has said the inventory is
     * complete and every source carries an amount. Summing a list nobody has
     * finished would state the funding total of a business from the part of
     * it somebody happened to record, and the sum of a partial list is not a
     * total. The same bar governs available funds, which is that total on top
     * of opening cash.
     *
     * @return list<Fact>
     */
    private function funding(SourceRecords $source, ForecastModelInputs $inputs): array
    {
        $facts = [];
        $complete = $inputs->fundingInventoryComplete;
        if ($complete !== null) {
            $facts[] = new Fact('funding.inventory_complete', $complete, confirmed: true,
                sources: [$source->reference('forecast_model.drivers.funding_inventory_complete')]);
        }
        $total = BigDecimal::zero();
        foreach ($inputs->fundingSources as $index => $funding) {
            $at = [$source->reference('forecast_model.drivers.funding_sources.'.$index)];
            $total = $funding->amount === null ? null : $total?->plus($funding->amount);
            $facts[] = new Fact('funding.amount', $funding->amount, $funding->id, unit: 'NZD',
                confirmed: $funding->verified === true, sources: $at);
            // Emitted whether or not they are answered. A key present with no
            // value is the shape of an arrangement still being established,
            // and the rule reads it as the gap it is; leaving the key out
            // would hide which part of the source is unestablished.
            foreach (['identity' => $funding->identity, 'type' => $funding->type, 'commitment' => $funding->commitment,
                'availability' => $funding->availableFrom, 'draw_date' => $funding->drawnOn,
                'restriction' => $funding->restriction] as $key => $value) {
                $facts[] = new Fact('funding.'.$key, $value, $funding->id, confirmed: true, sources: $at);
            }
        }
        if ($complete !== true || $total === null) {
            return $facts;
        }
        $at = [$source->reference('forecast_model.drivers.funding_sources')];
        $facts[] = new Fact('model.funding_total', (string) $total, unit: 'NZD', confirmed: true, sources: $at);
        $opening = $inputs->cashOpening?->amount;
        if ($opening !== null) {
            $facts[] = new Fact('model.available_funds', (string) $total->plus($opening), unit: 'NZD', confirmed: true,
                sources: [...$at, $source->reference('forecast_model.drivers.cash_opening')]);
        }

        return $facts;
    }

    /** @return list<Fact> */
    private function model(SourceRecords $source, ForecastModelInputs $inputs): array
    {
        $at = [$source->reference('forecast_model.drivers.calendar')];

        return [
            new Fact('model.start_date', $inputs->calendar->startMonth.'-01', confirmed: true, sources: $at),
            new Fact('model.start_period', $inputs->calendar->startMonth, confirmed: true, sources: $at),
            // The date this reading of the plan was taken. The elapsed-actuals
            // rule counts closed reporting months from it, so it is the
            // snapshot's own date rather than anything inside the model.
            new Fact('actuals.as_of', $source->version->asOf, confirmed: true, sources: [$source->reference('forecast_model')]),
        ];
    }

    /**
     * Per-stream and per-month driver facts.
     *
     * A stream nobody has classified emits no membership fact at all. The
     * rules that read it require a boolean, and an absent flag reported as
     * missing is the finding; a flag invented here would be the defect.
     *
     * @return list<Fact>
     */
    private function streams(SourceRecords $source, ForecastModelInputs $inputs): array
    {
        $facts = [];
        $run = $this->run->revenue($inputs);
        $months = array_column($run['months'], null, 'period');
        foreach ($inputs->calendar->periods as $offset => $period) {
            $label = 'month:'.($offset + 1);
            $month = $months[$period] ?? null;
            $facts[] = new Fact('model.base_revenue', $month['base_revenue'] ?? null, period: $label, unit: 'NZD',
                confirmed: true, sources: [$source->reference('forecast_model.drivers.streams')]);
            $facts[] = new Fact('capacity.available_hours', $month['founder_hours_available'] ?? null, period: $label,
                unit: 'hours', confirmed: true, sources: [$source->reference('forecast_model.drivers.founder_months')]);
        }
        foreach ($inputs->streams as $index => $stream) {
            $at = [$source->reference('forecast_model.drivers.streams.'.$index)];
            if ($stream->inBaseCase !== null) {
                $facts[] = new Fact('stream.membership', $stream->inBaseCase, $stream->id, confirmed: true, sources: $at);
            }
            $facts[] = new Fact('stream.preparation_hours_per_unit', $stream->preparationHoursPerUnit, $stream->id,
                unit: 'hours', confirmed: true, sources: $at);
            $facts[] = new Fact('stream.delivery_hours_per_unit', $stream->deliveryHoursPerUnit, $stream->id,
                unit: 'hours', confirmed: true, sources: $at);
            $provider = $this->provider($stream);
            if ($provider !== null) {
                $facts[] = new Fact('capacity.provider', $provider, $stream->id, confirmed: true, sources: $at);
            }
            $facts = [...$facts, ...$this->streamMonths($source, $inputs, $stream, $index)];
        }

        return $facts;
    }

    /** @return list<Fact> */
    private function streamMonths(SourceRecords $source, ForecastModelInputs $inputs, RevenueStream $stream, int $index): array
    {
        $facts = [];
        $previous = null;
        foreach ($inputs->calendar->periods as $offset => $period) {
            $month = $stream->schedule[$period] ?? null;
            if ($month === null) {
                $previous = null;

                continue;
            }
            $label = 'month:'.($offset + 1);
            $at = [$source->reference('forecast_model.drivers.streams.'.$index.'.schedule.'.$period)];
            $facts[] = new Fact('stream.units', $month->deliveryUnits, $stream->id, $label, unit: 'units', confirmed: true, sources: $at);
            if ($month->provider !== null && trim($month->provider) !== '') {
                $facts[] = new Fact('provider.cost', $month->providerUnitCost, $month->provider, $label, unit: 'NZD/unit', confirmed: true, sources: $at);
                if ($month->noCostBasis !== null) {
                    $facts[] = new Fact('provider.no_cost_basis', $month->noCostBasis, $month->provider, $label, confirmed: true, sources: $at);
                }
            }
            // Month-on-month growth, and only where both months are known.
            // A ramp measured from an absent month would read as a step up
            // from zero rather than as a month nobody has filled in.
            $ramp = $this->ramp($previous, $month->deliveryUnits);
            if ($ramp !== null) {
                $facts[] = new Fact('model.ramp', $ramp, $stream->id, $label, unit: 'percent', confirmed: true, sources: $at);
            }
            $previous = $month->deliveryUnits;
        }

        return $facts;
    }

    /**
     * One provider for the stream, or none.
     *
     * A stream whose provider changes from month to month has no single
     * provider, and naming one of them would be a claim nobody made. The
     * capacity rule then reports the provider as missing, which is the
     * truthful reading of a stream delivered by several.
     */
    private function provider(RevenueStream $stream): ?string
    {
        $named = [];
        foreach ($stream->schedule as $month) {
            $named[] = $month->provider === null || trim($month->provider) === '' ? 'founder' : trim($month->provider);
        }
        $distinct = array_values(array_unique($named));

        return count($distinct) === 1 ? $distinct[0] : null;
    }

    /** @return list<Fact> */
    private function actuals(SourceRecords $source, ForecastModelInputs $inputs): array
    {
        $facts = [];
        $offsets = array_flip($inputs->calendar->periods);
        foreach ($inputs->actualMonths as $index => $month) {
            if (! isset($offsets[$month->period])) {
                continue;
            }
            $label = 'month:'.($offsets[$month->period] + 1);
            $at = [$source->reference('forecast_model.actuals.months.'.$index)];
            // Reconciled and pre-trading are the two answers the rule accepts.
            // A trading month nobody has reconciled is neither, and emitting
            // no status is what makes it report as the gap it is.
            $status = $month->state === 'pre_trading' ? 'pre_trading' : ($month->source->reconciled === true ? 'reconciled' : null);
            if ($status !== null) {
                $facts[] = new Fact('actuals.status', $status, period: $label, confirmed: true, sources: $at);
                $facts[] = new Fact('period.label', 'actual', period: $label, confirmed: true, sources: $at);
            }
            $facts[] = new Fact('actuals.closing_cash', $month->closing->bankCash, period: $label, unit: 'NZD',
                confirmed: $month->source->reconciled === true, sources: $at);
        }

        return $facts;
    }

    /**
     * @param  CashResult  $run
     * @return list<Fact>
     */
    private function cash(SourceRecords $source, ForecastModelInputs $inputs, array $run): array
    {
        $facts = [];
        $reviewed = [];
        foreach ($inputs->actualMonths as $month) {
            $reviewed[$month->period] = true;
        }
        $offsets = array_flip($inputs->calendar->periods);
        foreach ($run['months'] as $month) {
            if (isset($reviewed[$month['period']]) || ! isset($offsets[$month['period']])) {
                continue;
            }
            $label = 'month:'.($offsets[$month['period']] + 1);
            $facts[] = new Fact('forecast.opening_cash', $month['opening_cash'], period: $label, unit: 'NZD',
                confirmed: true, sources: [$source->reference('forecast_model.drivers.cash_opening')]);
            $facts[] = new Fact('period.label', 'forecast', period: $label, confirmed: true,
                sources: [$source->reference('forecast_model.drivers.calendar')]);
        }
        $at = [$source->reference('forecast_model.reserve_policy')];
        $facts[] = new Fact('scenario.cash_deficit', $run['cash_deficit'], unit: 'NZD', confirmed: true, sources: $at);
        $facts[] = new Fact('scenario.reserve_gap', $run['reserve_shortfall'], unit: 'NZD', confirmed: true, sources: $at);

        return $facts;
    }

    /**
     * The dated, classified and sourced cash ledger the reconstruction holds.
     *
     * This is evidence MI-01 has never had. Nothing on the platform produced
     * a ledger entry before this, so every month reported its ledger as
     * missing however much the stored budget said about the balances; the
     * rule could reach Cannot verify and no further. The balances here are
     * the ledger's own, not the budget's, which is why they displace it.
     *
     * @param  CashResult  $run
     * @return list<Fact>
     */
    private function ledger(SourceRecords $source, ForecastModelInputs $inputs, array $run): array
    {
        $opening = $inputs->cashOpening;
        $at = [$source->reference('forecast_model.drivers')];
        // The opening balance is dated at the month the forecast opens, which
        // is where the ledger puts its own first checkpoint. Its account and
        // restriction scope stays absent because the reconstruction records
        // neither: a basis read off the source line would be a claim about
        // restriction that nobody made.
        $facts = [new Fact('model.opening_cash', $opening?->amount, unit: 'NZD', asOf: $inputs->calendar->startMonth.'-01',
            confirmed: $opening?->verified === true, sources: [$source->reference('forecast_model.drivers.cash_opening')])];
        $offsets = array_flip($inputs->calendar->periods);
        // Which declared source each financing inflow came from. The rule
        // requires every one of them to map to a named source, and the map is
        // built from the inputs rather than guessed from the movement, so a
        // source nobody has named leaves its draw unmapped and reported.
        $funders = [];
        foreach ($inputs->fundingSources as $funding) {
            $funders[$funding->id.'.draw'] = $funding->identity;
        }
        // The curve itself comes from the shared emitter, because SS-01 checks
        // each scenario's curve against the same arithmetic MI-01 checks this
        // one against, and two implementations of it would drift.
        $facts = [...$facts, ...$this->curve->capture($source, $inputs->calendar, $run['months'], $at)];
        // The base case's trough is stated here rather than with the scenario
        // troughs, because it needs none of the scenario machinery - no
        // actuals window, no catalogue, no shocks - and the budget's record
        // under this key is superseded whether or not a scenario set can be
        // built. Leaving it to the scenario producer would take the figure off
        // the budget and replace it with nothing on every plan whose scenarios
        // cannot be established.
        $trough = $this->curve->trough($run['months']);
        if ($trough !== null) {
            $facts[] = new Fact('scenario.lowest_cash', $trough, unit: 'NZD',
                basis: 'lowest_month_end_closing_balance', confirmed: true, sources: $at);
        }
        // The base case's own baseline and shocks, stated here for the reason
        // its trough is: SS-01 reads the base case whenever the reconstruction
        // closes its months, which happens whether or not a scenario set can
        // be built. Left to the scenario run, the base case would report its
        // inputs unestablished on every plan without one.
        $facts[] = new Fact('scenario.baseline', ScenarioFacts::baseline($inputs),
            basis: 'unshocked_reconstruction', confirmed: true, sources: $at);
        // Not absent, and not a placeholder: the base case declares no shock,
        // and an empty declaration is what says so.
        $facts[] = new Fact('scenario.shocks', '[]', basis: 'the_base_case_declares_no_shock',
            confirmed: true, sources: $at);
        foreach ($run['movements'] as $movement) {
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
            $facts[] = new Fact('ledger.amount', $movement->amount, $movement->id, $label, unit: 'NZD', confirmed: true, sources: $at);
            $facts[] = new Fact('ledger.date', $movement->date, $movement->id, $label, confirmed: true, sources: $at);
            // The record and the movement within it. One invoice can settle in
            // instalments, and the rule requires each ledger line to be
            // identifiable once; the record on its own would read as the same
            // line counted twice.
            $facts[] = new Fact('ledger.source', $movement->source.' ('.$movement->id.')', $movement->id, $label, confirmed: true, sources: $at);
            $category = $this->category($movement);
            if ($category !== null) {
                $facts[] = new Fact('ledger.category', $category, $movement->id, $label, confirmed: true, sources: $at);
            }
            if (($funders[$movement->id] ?? null) !== null) {
                $facts[] = new Fact('ledger.funding_identity', $funders[$movement->id], $movement->id, $label, confirmed: true, sources: $at);
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
     * MI-01 report the entry rather than reconcile it. A tax or GST refund and
     * an asset disposal are receipts, but the rule's three inflow categories
     * are customer receipts, other operating receipts and financing inflows,
     * and none of them describes a refund of tax or the proceeds of a disposal
     * without asserting something about its nature. A restricted transfer is
     * a movement between restricted and unrestricted cash, which the rule's
     * categories do not cover at all. Filing any of them under a category
     * that does not fit would let a month reconcile on a classification the
     * gate's own taxonomy cannot support; reporting it unclassified is the
     * honest outcome and names the entry to the advisor.
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

    private function ramp(?string $previous, ?string $current): ?string
    {
        if ($previous === null || $current === null) {
            return null;
        }
        $from = BigDecimal::of($previous);
        if ($from->isZero()) {
            return null;
        }

        return (string) BigDecimal::of($current)->minus($from)->multipliedBy(100)->dividedBy($from, 4, RoundingMode::HalfUp);
    }
}
