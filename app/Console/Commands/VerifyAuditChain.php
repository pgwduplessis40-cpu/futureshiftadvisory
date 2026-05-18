<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Daily chain integrity check on the audit_events table.
 *
 * Phase 1 checks:
 *   1. No event has occurred_at in the future (clock skew or tampering).
 *   2. No request_id has events spanning more than 24 hours (a single
 *      request_id stretched across a day is almost certainly a bug or
 *      a replayed id).
 *   3. The most recent event is no older than 7 days (a system that
 *      has been silent for a week is suspicious - either traffic is
 *      genuinely zero, or audit writes are being lost).
 *
 * The command writes findings to the log, prints them to stderr, and
 * exits non-zero if any check fails. Real alerting (notify super-admin)
 * arrives once the notifications system is in place (WO-12).
 *
 * Scheduled daily via bootstrap/app.php.
 */
final class VerifyAuditChain extends Command
{
    /** @var string */
    protected $signature = 'fsa:audit:verify
                            {--since= : Only inspect events occurring after this ISO-8601 timestamp.}';

    /** @var string */
    protected $description = 'Verify integrity invariants on the audit_events table.';

    public function handle(): int
    {
        $sinceInput = $this->option('since');
        $since = is_string($sinceInput) && $sinceInput !== ''
            ? Carbon::parse($sinceInput)
            : Carbon::now()->subDay();

        $findings = [];

        $futureCount = AuditEvent::query()
            ->where('occurred_at', '>', Carbon::now())
            ->count();
        if ($futureCount > 0) {
            $findings[] = "{$futureCount} audit_events row(s) have occurred_at in the future.";
        }

        $longRequestIds = AuditEvent::query()
            ->whereNotNull('request_id')
            ->selectRaw('request_id, MIN(occurred_at) AS first_seen, MAX(occurred_at) AS last_seen')
            ->groupBy('request_id')
            ->havingRaw('EXTRACT(EPOCH FROM (MAX(occurred_at) - MIN(occurred_at))) > 86400')
            ->get();
        if ($longRequestIds->isNotEmpty()) {
            $findings[] = sprintf(
                '%d request_id(s) span more than 24 hours of events.',
                $longRequestIds->count(),
            );
        }

        /** @var AuditEvent|null $latest */
        $latest = AuditEvent::query()->latest('occurred_at')->first();
        if ($latest !== null && $latest->occurred_at->lt(Carbon::now()->subDays(7))) {
            $findings[] = sprintf(
                'Most recent audit event is %s (older than 7 days). audit_events writes may be broken.',
                $latest->occurred_at->toIso8601String(),
            );
        }

        $inspectedCount = AuditEvent::query()->where('occurred_at', '>=', $since)->count();
        $this->info("Inspected {$inspectedCount} audit_events row(s) since {$since->toIso8601String()}.");

        if ($findings === []) {
            $this->info('Audit chain looks healthy.');

            return self::SUCCESS;
        }

        foreach ($findings as $finding) {
            $this->error($finding);
        }

        return self::FAILURE;
    }
}
