<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

/**
 * Runs a stored reconstruction through the forecast engine.
 *
 * The engine was a library with no caller: every component unit-tested, and
 * nothing assembling them from a stored reconstruction. This is that
 * boundary. It runs the revenue projection and the dated cash side, and it
 * stays narrow on purpose: the scenario run and the tax projection need
 * inputs the reconstruction does not yet hold, so they are not run here
 * rather than run against a model that cannot support them.
 *
 * Its first job is honesty about what the reconstruction can support. A
 * projection that cannot run is reported as a run that failed, with the
 * reason, rather than allowed to surface as an engine crash on a screen an
 * advisor is reading.
 *
 * @phpstan-type RunMonth array{period:string,status:string,base_revenue:string|null,base_contractor_cost:string|null,founder_hours_allocated:string|null,founder_hours_available:string|null}
 * @phpstan-type RunGap array{reason:string,months:int,first_period:string}
 * @phpstan-type RunResult array{engine_version:string,status:string,months:list<RunMonth>,missing:list<RunGap>,errors:list<RunGap>,refusal:string|null}
 * @phpstan-type CashMonth array{period:string,opening_cash:string|null,receipts:string|null,payments:string|null,closing_cash:string|null}
 * @phpstan-type CashResult array{engine_version:string,status:string,movements_counted:int,settled_before_opening:int,lowest_cash:string|null,lowest_cash_date:string|null,cash_deficit:string|null,reserve_target:string|null,reserve_shortfall:string|null,months:list<CashMonth>,movements:list<CashMovement>,missing:list<RunGap>,refusal:string|null}
 * @phpstan-type CostMonth array{period:string,expense:string|null,paid:string|null,accrual_movement:string|null,payments:list<CashMovement>}
 * @phpstan-type CostLine array{id:string,source:string,rate:string|null,quantity:string|null,frequency:string|null,reviewed:bool|null,recognition:string|null,invoices:int,months:list<CostMonth>,missing:list<string>}
 * @phpstan-type CostResult array{engine_version:string,status:string,lines:list<CostLine>,totals:array<string,string|null>,movements:list<CashMovement>,missing:list<RunGap>,refusal:string|null}
 *
 * @phpstan-import-type ProfitResult from MonthlyProfit
 */
final class ForecastRun implements ProvidesMethodology
{
    public const VERSION = 'phase-2e.1';

    /**
     * The reconstruction the two results below belong to.
     *
     * One pass over a reconstruction asks for the revenue projection three
     * times — the cash side needs it for owner pay, the stream facts read it,
     * and the profit decomposition reads it again — and for the expense
     * schedules twice. Both are pure functions of an immutable input, so the
     * answer cannot differ between calls; recomputing it only costs time, and
     * a gate evaluation runs inside an HTTP request.
     *
     * One reconstruction is remembered rather than a map of them, because
     * that is the shape of the work: a producer runs one model and asks
     * repeatedly about it. The input is compared by identity, so a different
     * reconstruction never reads another's answer, and at most one is held
     * alive by this.
     */
    private ?ForecastModelInputs $computedFor = null;

    /** @var RunResult|null */
    private ?array $computedRevenue = null;

    /** @var CostResult|null */
    private ?array $computedCosts = null;

    public static function methodologyIds(): array
    {
        return ['entrepreneur.driver_revenue', 'entrepreneur.dated_cash'];
    }

    /** @return RunResult */
    public function revenue(ForecastModelInputs $inputs): array
    {
        $this->remember($inputs);
        $this->computedRevenue ??= $this->project($inputs);

        return $this->computedRevenue;
    }

    /** Clears what was remembered when the reconstruction being asked about changes. */
    private function remember(ForecastModelInputs $inputs): void
    {
        if ($this->computedFor === $inputs) {
            return;
        }
        $this->computedFor = $inputs;
        $this->computedRevenue = null;
        $this->computedCosts = null;
    }

    /** @return RunResult */
    private function project(ForecastModelInputs $inputs): array
    {
        if ($inputs->streams === []) {
            // No streams is not a failed projection; it is a projection with
            // nothing to project. Saying so is more use than a row of zeroes.
            return $this->result('cannot_verify', [], [['reason' => 'No revenue streams are recorded.', 'months' => 0, 'first_period' => $inputs->calendar->startMonth]], [], null);
        }

        try {
            $projection = (new RevenueProjection)->calculate($inputs->calendar, $inputs->streams, $inputs->founderMonths);
        } catch (\InvalidArgumentException $e) {
            // The projection refuses a reconstruction it cannot read rather
            // than discarding part of it. Both forms now refuse to store one
            // in that state, but a model stored before they did still exists.
            return $this->result('fail', [], [], [], $e->getMessage());
        }

        $months = [];
        $status = 'pass';
        $missing = $errors = [];
        foreach ($projection['months'] as $month) {
            $months[] = ['period' => $month['period'], 'status' => $month['status'], 'base_revenue' => $month['base_revenue'],
                // The direct cost of delivering that revenue, carried through
                // beside it because a profit decomposition needs both halves
                // and deriving one from the other would state a margin nobody
                // recorded.
                'base_contractor_cost' => $month['base_contractor_cost'],
                'founder_hours_allocated' => $month['founder_hours_allocated'], 'founder_hours_available' => $month['founder_hours_available']];
            $status = $month['status'] === 'fail' || $status === 'fail' ? 'fail' : ($month['status'] === 'cannot_verify' ? 'cannot_verify' : $status);
            $this->gather($missing, $month['missing'], $month['period']);
            $this->gather($errors, $month['errors'], $month['period']);
        }

        return $this->result($status, $months, array_values($missing), array_values($errors), null);
    }

    /**
     * Runs the dated cash side: the settlements, owner pay and asset
     * purchases the reconstruction holds, through the ledger and the funding
     * measures that read it.
     *
     * The two measures the funding result carries are deliberately separable
     * and never summed. A cash deficit is already inside the reserve
     * shortfall, so a reader who adds them double-counts; they are passed
     * through as the engine reports them rather than combined here.
     *
     * @return CashResult
     */
    public function cash(ForecastModelInputs $inputs): array
    {
        $opening = $inputs->cashOpening;
        $missing = [];
        if ($opening === null) {
            return $this->cashResult('cannot_verify', 0, 0, null, null, [$this->gap('No opening cash position is recorded.', $inputs)], null);
        }

        try {
            [$movements, $early, $dated, $flat] = $this->movements($inputs);
            $ledger = (new CashLedger)->calculate($inputs->calendar, $opening, $movements);
        } catch (\InvalidArgumentException $e) {
            // The ledger refuses a set of movements it cannot position rather
            // than ordering them arbitrarily, and the schedules refuse inputs
            // they cannot read. Either way an advisor sees the reason.
            return $this->cashResult('fail', 0, 0, null, null, [], $e->getMessage());
        }
        // A weekly pay series missing its rate reports the same gap on every
        // payment date. Those are one gap with a reach, exactly as the
        // monthly ones are; the ledger's own gaps are one-off and stay so.
        $counted = [];
        foreach ($dated as [$reason, $date]) {
            $this->gather($counted, [$reason], $date);
        }
        $missing = array_values($counted);
        foreach ([...$flat, ...$ledger['missing']] as $reason) {
            $missing[] = $this->gap($reason, $inputs);
        }
        $funding = $inputs->reservePolicy === null ? null : (new FundingMeasures)->calculate($ledger, $inputs->reservePolicy);
        foreach ($funding['missing'] ?? [] as $reason) {
            $missing[] = $this->gap($reason, $inputs);
        }

        return [
            'engine_version' => self::VERSION,
            'status' => $missing === [] ? 'pass' : 'cannot_verify',
            'movements_counted' => count($movements),
            // Cash dated before the forecast opens is already in the opening
            // balance; the ledger refuses it rather than counting it twice.
            'settled_before_opening' => $early,
            'lowest_cash' => $ledger['lowest_cash'], 'lowest_cash_date' => $ledger['lowest_cash_date'],
            'cash_deficit' => $funding['cash_deficit'] ?? null,
            'reserve_target' => $funding['reserve_target'] ?? null,
            'reserve_shortfall' => $funding['reserve_shortfall'] ?? null,
            // Carried through so a reader can see where the forecast resumes
            // after the last reconciled month, which is the handover the
            // elapsed-actuals rule checks. Receipts and payments come with
            // them because the ledger's own net movement for a month is what
            // the cash-ledger rule reconciles against the two balances, and
            // deriving it from the balances instead would re-state the
            // ledger's arithmetic rather than read it.
            'months' => array_map(
                fn (array $month): array => ['period' => $month['period'], 'opening_cash' => $month['opening_cash'],
                    'receipts' => $month['receipts'], 'payments' => $month['payments'], 'closing_cash' => $month['closing_cash']],
                $ledger['months'],
            ),
            // The positioned movements themselves, so a caller can report the
            // dated and classified line behind each month rather than only the
            // month's total. They arrive in the ledger's own order.
            'movements' => $ledger['movements'],
            'missing' => $missing, 'refusal' => null,
        ];
    }

    /**
     * Monthly profit before tax, from the sections the reconstruction holds.
     *
     * This is the decomposition the tax chain needs for a forecast month and
     * the scenario rules need per scenario. It assembles rather than
     * calculates: the revenue projection, the fixed-cost recognition, the
     * asset schedules and the owner-pay schedule each already state their own
     * month, and the trade items are added to them because they are opening
     * debtors and creditors plus one-offs rather than a restatement of either.
     *
     * The cash run is taken as an argument rather than re-run, because the
     * owner-pay schedule it contains is expensive and running it twice would
     * be a second answer to the same question.
     *
     * @param  CashResult  $cash
     * @return ProfitResult
     */
    public function profit(ForecastModelInputs $inputs, array $cash): array
    {
        $revenue = [];
        foreach ($this->revenue($inputs)['months'] as $month) {
            $revenue[$month['period']] = ['base_revenue' => $month['base_revenue'],
                'base_contractor_cost' => $month['base_contractor_cost']];
        }
        $assets = array_fill_keys($inputs->calendar->periods, ['depreciation' => '0.00', 'expense' => '0.00']);
        foreach ($inputs->assets as $asset) {
            foreach ((new AssetSchedule)->calculate($inputs->calendar, $asset, 0)['months'] as $month) {
                $period = $month['period'];
                // One asset that cannot be scheduled withholds the month for
                // all of them: a depreciation total summed over the assets
                // that happen to be readable is not the month's depreciation.
                foreach (['depreciation', 'expense'] as $part) {
                    $assets[$period][$part] = $assets[$period][$part] === null || $month[$part] === null
                        ? null : (string) BigDecimal::of($assets[$period][$part])->plus($month[$part]);
                }
            }
        }
        $ownerPay = [];
        foreach ($cash['movements'] as $movement) {
            if ($movement->category !== 'owner_pay' || $movement->date === null || $movement->amount === null) {
                continue;
            }
            $period = substr($movement->date, 0, 7);
            $ownerPay[$period] = (string) BigDecimal::of($ownerPay[$period] ?? '0')->plus($movement->amount);
        }

        return (new MonthlyProfit)->calculate($inputs->calendar, $revenue, $this->fixedCosts($inputs)['totals'],
            $inputs->tradeItems, $assets, $ownerPay, $inputs->ownerPayDeductible);
    }

    /**
     * Runs the recurring overheads: what each one recognises every month, the
     * invoices it raises, and the accrual position between the two.
     *
     * Recognition and payment are deliberately separate answers. A fixed cost
     * relates to the period it covers, so it is recognised straight-line at
     * its annualised rate; the invoices fall whenever the arrangement says
     * they fall. The gap between them in a month is the accrual the cost
     * carries, which is a position rather than a discrepancy, and reporting
     * the two as one figure would hide an annual premium paid in advance.
     *
     * A cost missing its cadence or its invoice window raises nothing and
     * recognises nothing. Assuming a cadence would put twelve charges a year
     * behind a cost nobody has said recurs monthly.
     *
     * @return CostResult
     */
    public function fixedCosts(ForecastModelInputs $inputs, int $sequence = 0): array
    {
        // Only the default numbering is remembered. A caller that asks for a
        // sequence block is positioning these payments among others in the
        // ledger, and the movements it gets back carry that numbering, so a
        // cached answer from a different block would be the wrong one.
        if ($sequence !== 0) {
            return $this->schedule($inputs, $sequence);
        }
        $this->remember($inputs);
        $this->computedCosts ??= $this->schedule($inputs, 0);

        return $this->computedCosts;
    }

    /** @return CostResult */
    private function schedule(ForecastModelInputs $inputs, int $sequence): array
    {
        $schedule = new ExpenseSchedule;
        $lines = $movements = $missing = [];
        // Null until a cost recognises something into the month, and null
        // again as soon as one cost cannot be valued: a total summed over the
        // costs that happen to be priced is not the month's fixed cost.
        $totals = array_fill_keys($inputs->calendar->periods, null);
        $unknown = [];
        foreach ($inputs->fixedCosts as $cost) {
            $recognition = $schedule->monthlyRecognition($cost->rate, $cost->quantity, $cost->frequency);
            $gaps = [];
            foreach (['rate' => $cost->rate, 'quantity' => $cost->quantity, 'frequency' => $cost->frequency,
                'first_invoice_on' => $cost->firstInvoiceOn, 'last_invoice_on' => $cost->lastInvoiceOn] as $field => $value) {
                if ($value === null) {
                    $gaps[] = $field;
                }
            }
            if ($cost->creditorDays === null) {
                $gaps[] = 'creditor_days';
            }
            $obligations = [];
            if ($cost->frequency !== null && $cost->firstInvoiceOn !== null && $cost->lastInvoiceOn !== null) {
                try {
                    $obligations = $schedule->fixed($inputs->calendar, $cost->id, $cost->source, $cost->rate, $cost->quantity,
                        $cost->frequency, $cost->firstInvoiceOn, $cost->lastInvoiceOn,
                        array_fill_keys($inputs->calendar->periods, '1'), $cost->creditorDays, $sequence)['obligations'];
                } catch (\InvalidArgumentException $e) {
                    // An invoice window the schedule will not accept produces
                    // no invoices at all. The reason is carried rather than
                    // swallowed, because a cost that raises nothing and a cost
                    // whose window was refused are different states.
                    return $this->costResult('fail', [], $totals, [], [], $e->getMessage());
                }
            }
            $sequence++;
            // Nothing paid is zero paid; an invoice nobody has priced is
            // unknown. Collapsing the two would read an unpriced invoice as a
            // month with no payment, and the accrual would absorb the whole
            // expense as though the cost had simply not been billed yet.
            $paid = array_fill_keys($inputs->calendar->periods, '0');
            $unpriced = $settlements = [];
            foreach ($obligations as $obligation) {
                foreach ($obligation->settlements ?? [] as $settlement) {
                    $movements[] = $settlement;
                    $period = $settlement->date === null ? null : substr($settlement->date, 0, 7);
                    if ($period === null || ! array_key_exists($period, $paid)) {
                        // A payment falling past the calendar is deferred by
                        // the ledger and belongs to no month here either. The
                        // expense is still recognised, so the accrual carries
                        // it, which is what an invoice paid after the forecast
                        // ends actually leaves behind.
                        continue;
                    }
                    $settlements[$period][] = $settlement;
                    if ($settlement->amount === null) {
                        $unpriced[$period] = true;
                    } else {
                        $paid[$period] = (string) BigDecimal::of($paid[$period])->plus($settlement->amount);
                    }
                }
            }
            $months = [];
            foreach ($inputs->calendar->periods as $period) {
                $cash = isset($unpriced[$period]) ? null : $paid[$period];
                $months[] = ['period' => $period, 'expense' => $recognition, 'paid' => $cash,
                    // Expense recognised less cash paid. Positive is an
                    // accrual, negative a prepayment; both are ordinary.
                    'accrual_movement' => $recognition === null || $cash === null
                        ? null : (string) BigDecimal::of($recognition)->minus($cash),
                    'payments' => $settlements[$period] ?? []];
                if ($recognition === null) {
                    $unknown[$period] = true;

                    continue;
                }
                $totals[$period] = (string) BigDecimal::of($totals[$period] ?? '0')->plus($recognition);
            }
            $lines[] = ['id' => $cost->id, 'source' => $cost->source, 'rate' => $cost->rate, 'quantity' => $cost->quantity,
                'frequency' => $cost->frequency, 'reviewed' => $cost->reviewed, 'recognition' => $recognition,
                'invoices' => count($obligations), 'months' => $months, 'missing' => $gaps];
            foreach ($gaps as $gap) {
                $missing[] = $this->gap($cost->id.'.'.$gap, $inputs);
            }
        }
        foreach (array_keys($unknown) as $period) {
            $totals[$period] = null;
        }

        return $this->costResult($missing === [] ? 'pass' : 'cannot_verify', $lines, $totals, $movements, $missing, null);
    }

    /**
     * @param  list<CostLine>  $lines
     * @param  array<string,string|null>  $totals
     * @param  list<CashMovement>  $movements
     * @param  list<RunGap>  $missing
     * @return CostResult
     */
    private function costResult(string $status, array $lines, array $totals, array $movements, array $missing, ?string $refusal): array
    {
        return ['engine_version' => self::VERSION, 'status' => $status, 'lines' => $lines, 'totals' => $totals,
            'movements' => $movements, 'missing' => $missing, 'refusal' => $refusal];
    }

    /**
     * Assembles every dated movement the reconstruction implies.
     *
     * Sequence numbers decide the order of two movements landing on one day,
     * and the ledger refuses two that claim the same position. Settlements
     * arrive carrying the numbers the trade form gave them, so owner pay and
     * each asset are numbered above the highest of those rather than from
     * zero, which is where a collision would otherwise come from.
     *
     * Public because the scenario run needs the built set before it is
     * ledgered: a collection-delay shock moves dates on these movements, and
     * `cash()` builds and ledgers in one step with no seam to apply it at.
     * Returned as the four parts rather than a ledger so the caller keeps the
     * gaps, which decide whether a scenario may publish a figure at all.
     *
     * @return array{list<CashMovement>,int,list<array{0:string,1:string}>,list<string>}
     */
    public function movements(ForecastModelInputs $inputs): array
    {
        $opens = $inputs->calendar->startMonth.'-01';
        $movements = [];
        $dated = $flat = [];
        $early = 0;
        $next = 0;
        foreach ($inputs->tradeItems as $item) {
            foreach ($item->settlements ?? [] as $settlement) {
                $next = max($next, $settlement->sequence + 1);
                if ($settlement->date !== null && $settlement->date < $opens) {
                    $early++;

                    continue;
                }
                $movements[] = $settlement;
            }
        }
        if ($inputs->ownerPaySteps !== []) {
            if ($inputs->ownerPaySource === null || $inputs->ownerPayFirstPaymentOn === null) {
                $flat[] = 'owner_pay.source_and_first_payment_date';
            } else {
                $revenue = [];
                foreach ($this->revenue($inputs)['months'] as $month) {
                    $revenue[$month['period']] = $month['base_revenue'];
                }
                $pay = (new OwnerPaySchedule)->calculate($inputs->calendar, $inputs->ownerPaySource,
                    $inputs->ownerPayFirstPaymentOn, $inputs->ownerPaySteps, $revenue, $next);
                $next++;
                $movements = [...$movements, ...$pay['payments']];
                foreach ($pay['missing'] as $reason) {
                    // The schedule reports a gap as "{payment date}.{field}".
                    // Splitting the date back out lets one unknown rate read
                    // as one gap across every date it touches.
                    [$date, $field] = array_pad(explode('.', $reason, 2), 2, null);
                    $dated[] = $field === null ? ['owner_pay.'.$date, $opens] : ['owner_pay.'.$field, $date];
                }
            }
        }
        foreach ($inputs->assets as $asset) {
            $schedule = (new AssetSchedule)->calculate($inputs->calendar, $asset, $next);
            $next++;
            $movements = [...$movements, ...($schedule['cash_movements'] ?? [])];
            foreach ([...$schedule['missing'], ...$schedule['errors']] as $reason) {
                $flat[] = $asset->id.'.'.$reason;
            }
        }
        // Recurring overheads are real cash leaving the business, so the
        // invoices they raise belong in the ledger beside the trade payables.
        // One sequence per cost, assigned inside the run: a cost's own
        // invoices never share a date, and two costs invoiced on the same day
        // hold different positions rather than colliding.
        $costs = $this->fixedCosts($inputs, $next);
        $next += count($inputs->fixedCosts);
        if ($costs['refusal'] !== null) {
            throw new \InvalidArgumentException($costs['refusal']);
        }
        foreach ($costs['movements'] as $payment) {
            if ($payment->date !== null && $payment->date < $opens) {
                $early++;

                continue;
            }
            $movements[] = $payment;
        }
        foreach ($costs['missing'] as $gap) {
            $flat[] = $gap['reason'];
        }
        // Tax and GST cash leaves the bank like any other payment, so the
        // schedule belongs in the ledger. Its own sequence block sits above
        // everything numbered so far, because the schedule numbers its rows
        // from zero and would otherwise collide with the trade settlements.
        $plan = $inputs->taxCashPlan;
        if ($plan !== null) {
            foreach ($plan->movements as $movement) {
                if ($movement->date !== null && $movement->date < $opens) {
                    $early++;

                    continue;
                }
                $movements[] = new CashMovement($movement->id, $movement->source, $movement->date, $movement->amount,
                    $movement->direction, $movement->category, $next + $movement->sequence, $movement->verified);
            }
            $next += count($plan->movements);
            foreach ($plan->issues()['missing'] as $reason) {
                $flat[] = 'tax_cash.'.$reason;
            }
            foreach ($plan->issues()['errors'] as $reason) {
                $flat[] = 'tax_cash.'.$reason;
            }
        }
        foreach ($inputs->fundingSources as $fundingSource) {
            // One sequence per source, so two drawn on one day hold different
            // positions in the ledger rather than colliding.
            $draw = $fundingSource->draw($next);
            $next++;
            if ($draw === null) {
                // An arrangement with no amount or no draw date puts nothing
                // in the ledger, and the gap says which of the two is absent
                // rather than letting the source read as funding that arrives.
                $flat[] = $fundingSource->id.'.'.($fundingSource->amount === null ? 'amount' : 'drawn_on');

                continue;
            }
            if ($draw->date !== null && $draw->date < $opens) {
                // Money drawn before the forecast opens is already inside the
                // opening balance. The form refuses to record one, so this is
                // the older-model path, and counting it would be the double
                // count a funding reconciliation exists to catch.
                $early++;

                continue;
            }
            $movements[] = $draw;
        }

        return [$movements, $early, $dated, $flat];
    }

    /** @return RunGap */
    private function gap(string $reason, ForecastModelInputs $inputs): array
    {
        return ['reason' => $reason, 'months' => 0, 'first_period' => $inputs->calendar->startMonth];
    }

    /**
     * @param  list<RunGap>  $missing
     * @return CashResult
     */
    private function cashResult(string $status, int $counted, int $early, ?string $lowest, ?string $lowestDate, array $missing, ?string $refusal): array
    {
        return ['engine_version' => self::VERSION, 'status' => $status, 'movements_counted' => $counted,
            'settled_before_opening' => $early, 'lowest_cash' => $lowest, 'lowest_cash_date' => $lowestDate,
            'cash_deficit' => null, 'reserve_target' => null, 'reserve_shortfall' => null, 'months' => [],
            'movements' => [], 'missing' => $missing, 'refusal' => $refusal];
    }

    /**
     * Gaps are counted by reason rather than listed per month. A sixty-month
     * calendar missing one field reports it sixty times, which buries the
     * other fifty-nine reasons rather than reporting them.
     *
     * @param  array<string,RunGap>  $into
     * @param  list<string>  $reasons
     */
    private function gather(array &$into, array $reasons, string $period): void
    {
        foreach ($reasons as $reason) {
            if (! isset($into[$reason])) {
                $into[$reason] = ['reason' => $reason, 'months' => 0, 'first_period' => $period];
            }
            $into[$reason]['months']++;
        }
    }

    /**
     * @param  list<RunMonth>  $months
     * @param  list<RunGap>  $missing
     * @param  list<RunGap>  $errors
     * @return RunResult
     */
    private function result(string $status, array $months, array $missing, array $errors, ?string $refusal): array
    {
        return ['engine_version' => self::VERSION, 'status' => $status, 'months' => $months,
            'missing' => $missing, 'errors' => $errors, 'refusal' => $refusal];
    }
}
