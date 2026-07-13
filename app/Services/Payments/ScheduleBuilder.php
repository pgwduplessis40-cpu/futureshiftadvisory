<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\ProposalStatus;
use App\Models\PaymentAuthority;
use App\Models\PaymentSchedule;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ScheduleBuilder
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly InstallmentScheduleBuilder $installments,
    ) {}

    /**
     * @param  array{cadence?: string, amount?: int|float|string|null, currency?: string|null, collection_day?: int|string|null, next_run_at?: CarbonInterface|string|null}  $input
     */
    public function create(Proposal $proposal, PaymentAuthority $authority, array $input, ?User $actor = null): PaymentSchedule
    {
        $proposal = $proposal->refresh();
        $authority = $authority->refresh();

        if ($proposal->status !== ProposalStatus::Signed) {
            throw new InvalidArgumentException('Payment schedules require a signed proposal.');
        }

        if ((string) $authority->proposal_id !== (string) $proposal->getKey() || (string) $authority->client_id !== (string) $proposal->client_id) {
            throw new InvalidArgumentException('Payment authority must belong to the signed proposal.');
        }

        if ($authority->status !== PaymentAuthority::STATUS_ACTIVE || $authority->revoked_at !== null) {
            throw new InvalidArgumentException('Payment authority must be active before a schedule can be created.');
        }

        $cadence = (string) ($input['cadence'] ?? PaymentSchedule::CADENCE_ONE_OFF);

        if (! in_array($cadence, PaymentSchedule::cadences(), true)) {
            throw new InvalidArgumentException('Unsupported payment schedule cadence.');
        }

        $currency = strtoupper((string) ($input['currency'] ?? 'NZD'));

        if ($currency !== 'NZD') {
            throw new InvalidArgumentException('Payment schedules currently support NZD only.');
        }

        $amount = $this->normaliseAmount($input['amount'] ?? data_get($proposal->pv_summary, 'fee_suggested_mid'));
        $collectionDay = $this->normaliseCollectionDay($cadence, $input['collection_day'] ?? null);
        $nextRunAt = $this->normaliseNextRunAt($cadence, $input['next_run_at'] ?? null, $collectionDay);

        return DB::transaction(function () use ($proposal, $authority, $actor, $cadence, $amount, $currency, $collectionDay, $nextRunAt): PaymentSchedule {
            $schedule = PaymentSchedule::query()->create([
                'client_id' => $proposal->client_id,
                'proposal_id' => $proposal->getKey(),
                'payment_authority_id' => $authority->getKey(),
                'cadence' => $cadence,
                'amount' => $amount,
                'currency' => $currency,
                'collection_day' => $collectionDay,
                'next_run_at' => $nextRunAt,
                'status' => PaymentSchedule::STATUS_ACTIVE,
                'created_by_user_id' => $actor?->getKey(),
            ]);

            $this->audit->record('payment_schedule.created', subject: $schedule, actor: $actor, after: [
                'proposal_id' => $proposal->getKey(),
                'payment_authority_id' => $authority->getKey(),
                'cadence' => $cadence,
                'amount' => $amount,
                'currency' => $currency,
                'collection_day' => $collectionDay,
                'next_run_at' => $nextRunAt->toIso8601String(),
            ]);

            $this->installments->ensureFirstForIntegrationProposal($schedule, $proposal, $actor);

            return $schedule->refresh();
        });
    }

    public function revokeAuthority(PaymentAuthority $authority, ?User $actor = null): int
    {
        return DB::transaction(function () use ($authority, $actor): int {
            $authority = $authority->refresh();
            $now = now();

            if ($authority->status !== PaymentAuthority::STATUS_REVOKED || $authority->revoked_at === null) {
                $before = [
                    'status' => $authority->status,
                    'revoked_at' => $authority->revoked_at?->toIso8601String(),
                ];

                $authority->forceFill([
                    'status' => PaymentAuthority::STATUS_REVOKED,
                    'revoked_at' => $now,
                ])->save();

                $this->audit->record('payment_authority.revoked', subject: $authority, actor: $actor, before: $before, after: [
                    'status' => PaymentAuthority::STATUS_REVOKED,
                    'revoked_at' => $authority->revoked_at?->toIso8601String(),
                ]);
            }

            $revoked = 0;

            PaymentSchedule::query()
                ->where('payment_authority_id', $authority->getKey())
                ->whereIn('status', [PaymentSchedule::STATUS_ACTIVE, PaymentSchedule::STATUS_PAUSED])
                ->orderBy('created_at')
                ->each(function (PaymentSchedule $schedule) use ($actor, $authority, $now, &$revoked): void {
                    $before = [
                        'status' => $schedule->status,
                        'revoked_at' => $schedule->revoked_at?->toIso8601String(),
                    ];

                    $schedule->forceFill([
                        'status' => PaymentSchedule::STATUS_REVOKED,
                        'revoked_at' => $now,
                    ])->save();

                    $this->audit->record('payment_schedule.revoked', subject: $schedule, actor: $actor, before: $before, after: [
                        'payment_authority_id' => $authority->getKey(),
                        'status' => PaymentSchedule::STATUS_REVOKED,
                        'revoked_at' => $schedule->revoked_at?->toIso8601String(),
                    ]);

                    $revoked++;
                });

            return $revoked;
        });
    }

    private function normaliseAmount(mixed $amount): string
    {
        if (! is_int($amount) && ! is_float($amount) && ! (is_string($amount) && is_numeric($amount))) {
            throw new InvalidArgumentException('Payment schedule amount is required.');
        }

        $value = (float) $amount;

        if ($value <= 0) {
            throw new InvalidArgumentException('Payment schedule amount must be greater than zero.');
        }

        return number_format($value, 2, '.', '');
    }

    private function normaliseCollectionDay(string $cadence, mixed $value): ?int
    {
        if ($cadence !== PaymentSchedule::CADENCE_MONTHLY_RETAINER && ($value === null || $value === '')) {
            return null;
        }

        $day = is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value
            : 1;

        if (! in_array($day, [1, 15], true)) {
            throw new InvalidArgumentException('Payment collection date must be either the 1st or the 15th of the month.');
        }

        return $day;
    }

    private function normaliseNextRunAt(string $cadence, CarbonInterface|string|null $value, ?int $collectionDay): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            if ($collectionDay !== null && (int) $value->day !== $collectionDay) {
                throw new InvalidArgumentException('Payment schedule next run date must match the agreed collection date.');
            }

            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $parsed = CarbonImmutable::parse($value);

            if ($collectionDay !== null && (int) $parsed->day !== $collectionDay) {
                throw new InvalidArgumentException('Payment schedule next run date must match the agreed collection date.');
            }

            return $parsed;
        }

        if ($cadence === PaymentSchedule::CADENCE_MONTHLY_RETAINER) {
            return $this->nextMonthlyCollectionDate($collectionDay ?? 1);
        }

        return now();
    }

    private function nextMonthlyCollectionDate(int $collectionDay): CarbonInterface
    {
        $candidate = CarbonImmutable::now()
            ->startOfDay()
            ->setDay($collectionDay);

        if ($candidate->isPast()) {
            $candidate = $candidate->addMonthNoOverflow()->setDay($collectionDay);
        }

        return $candidate;
    }
}
