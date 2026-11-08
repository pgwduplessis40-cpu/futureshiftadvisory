<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Support\Methodology\ProvidesMethodology;

/**
 * Runs a stored reconstruction through the forecast engine.
 *
 * Until now the engine has been a library with no caller: every component is
 * unit-tested, and nothing assembles them from a stored reconstruction. This
 * is that boundary. It is deliberately narrow — it runs the revenue
 * projection, which needs nothing the stored model does not already hold —
 * and the cash side follows once the inputs it depends on exist.
 *
 * Its first job is honesty about what the reconstruction can support. A
 * projection that cannot run is reported as a run that failed, with the
 * reason, rather than allowed to surface as an engine crash on a screen an
 * advisor is reading.
 *
 * @phpstan-type RunMonth array{period:string,status:string,base_revenue:string|null,founder_hours_allocated:string|null,founder_hours_available:string|null}
 * @phpstan-type RunGap array{reason:string,months:int,first_period:string}
 * @phpstan-type RunResult array{engine_version:string,status:string,months:list<RunMonth>,missing:list<RunGap>,errors:list<RunGap>,refusal:string|null}
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
