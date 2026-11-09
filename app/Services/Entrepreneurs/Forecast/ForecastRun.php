<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Support\Methodology\ProvidesMethodology;

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
 * @phpstan-type RunMonth array{period:string,status:string,base_revenue:string|null,founder_hours_allocated:string|null,founder_hours_available:string|null}
 * @phpstan-type RunGap array{reason:string,months:int,first_period:string}
 * @phpstan-type RunResult array{engine_version:string,status:string,months:list<RunMonth>,missing:list<RunGap>,errors:list<RunGap>,refusal:string|null}
 * @phpstan-type CashMonth array{period:string,opening_cash:string|null,receipts:string|null,payments:string|null,closing_cash:string|null}
 * @phpstan-type CashResult array{engine_version:string,status:string,movements_counted:int,settled_before_opening:int,lowest_cash:string|null,lowest_cash_date:string|null,cash_deficit:string|null,reserve_target:string|null,reserve_shortfall:string|null,months:list<CashMonth>,movements:list<CashMovement>,missing:list<RunGap>,refusal:string|null}
 */
final class ForecastRun implements ProvidesMethodology
{
    public const VERSION = 'phase-2e.1';

    public static function methodologyIds(): array
    {
        return ['entrepreneur.driver_revenue'];
    }

    /** @return RunResult */
    public function revenue(ForecastModelInputs $inputs): array
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
     * Assembles every dated movement the reconstruction implies.
     *
     * Sequence numbers decide the order of two movements landing on one day,
     * and the ledger refuses two that claim the same position. Settlements
     * arrive carrying the numbers the trade form gave them, so owner pay and
     * each asset are numbered above the highest of those rather than from
     * zero, which is where a collision would otherwise come from.
     *
     * @return array{list<CashMovement>,int,list<array{0:string,1:string}>,list<string>}
     */
    private function movements(ForecastModelInputs $inputs): array
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
