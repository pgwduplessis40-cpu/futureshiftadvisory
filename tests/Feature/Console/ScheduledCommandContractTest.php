<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScheduledCommandContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_and_communication_scheduler_commands_succeed_when_no_work_is_due(): void
    {
        $this->artisan('screen-share:expire')->assertExitCode(0);
        $this->artisan('co-browse:expire')->assertExitCode(0);
        $this->artisan('communications:bulk-send')->assertExitCode(0);
        $this->artisan('service-journeys:reconcile')
            ->expectsOutput('Reconciled 0 enabled service journey(s).')
            ->assertExitCode(0);
        $this->artisan('npo:impact-summary-auto-release')->assertExitCode(0);
        $this->artisan('outcomes:schedule-follow-ups', ['--now' => '2026-08-24T09:00:00+12:00'])->assertExitCode(0);
        $this->artisan('fsa:audit:verify', ['--since' => '2026-08-23T09:00:00+12:00'])->assertExitCode(0);
    }

    public function test_learning_and_intelligence_commands_accept_deterministic_options_without_emitting_candidates(): void
    {
        $this->artisan('learning:active-layers', [
            '--at' => '2026-08-24T09:00:00+12:00',
            '--layer' => [1],
        ])->assertExitCode(0);
        $this->artisan('learning:cadence', [
            '--at' => '2026-08-24T09:00:00+12:00',
            '--layer' => [1],
        ])->assertExitCode(0);
        $this->artisan('learning:conversion-outcomes', ['--window-end' => '2026-08-24T09:00:00+12:00'])->assertExitCode(0);
        $this->artisan('learning:plan-quality-benchmarks', ['--window-end' => '2026-08-24T09:00:00+12:00'])->assertExitCode(0);
        $this->artisan('learning:dd-learning', ['--window-end' => '2026-08-24T09:00:00+12:00'])->assertExitCode(0);
        $this->artisan('learning:rating-validity-tests', [
            '--tested-at' => '2026-08-24T09:00:00+12:00',
            '--period' => '2026-H2',
        ])->assertExitCode(0);
        $this->artisan('intelligence:shared-layer', ['--generated-at' => '2026-08-24T09:00:00+12:00'])->assertExitCode(0);
        $this->artisan('intelligence:cross-client', ['--generated-at' => '2026-08-24T09:00:00+12:00'])->assertExitCode(0);
        $this->artisan('intelligence:benchmark-community', [
            'domain' => 'sme',
            'industry' => 'general',
            '--quarter' => '2026-Q3',
        ])->assertExitCode(0);
    }

    public function test_content_generation_commands_succeed_without_eligible_records(): void
    {
        $this->artisan('briefings:generate-monthly', ['--period' => '2026-08-24'])->assertExitCode(0);
        $this->artisan('templates:suggest', ['--window-end' => '2026-08-24T09:00:00+12:00'])->assertExitCode(0);
        $this->artisan('inspiration:select-weekly-quote')->assertExitCode(0);
        $this->artisan('panels:coach-signal-calibration')->assertExitCode(0);
        $this->artisan('fsa:ensure-built-in-surveys', ['--service-only' => true])->assertExitCode(0);
    }
}
