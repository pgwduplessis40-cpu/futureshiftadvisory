<?php

declare(strict_types=1);

namespace App\Services\Fees;

use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\PilotFeeWaiverProgram;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class PilotFeeWaiverManager
{
    public function currentProgram(): PilotFeeWaiverProgram
    {
        $program = PilotFeeWaiverProgram::query()
            ->where('key', PilotFeeWaiverProgram::KEY_DEFAULT)
            ->first();

        if ($program instanceof PilotFeeWaiverProgram) {
            return $program;
        }

        return new PilotFeeWaiverProgram([
            'key' => PilotFeeWaiverProgram::KEY_DEFAULT,
            'status' => PilotFeeWaiverProgram::STATUS_CLOSED,
        ]);
    }

    public function updateProgram(string $status, User $actor): PilotFeeWaiverProgram
    {
        return PilotFeeWaiverProgram::query()->updateOrCreate(
            ['key' => PilotFeeWaiverProgram::KEY_DEFAULT],
            [
                'status' => $status,
                'updated_by_user_id' => $actor->getKey(),
            ],
        );
    }

    /**
     * @return array{eligible:bool, program_status:string, starts_at:?string, expires_at:?string}
     */
    public function eligibility(Client|EntrepreneurProfile $subject, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $program = $this->currentProgram();
        $startsAt = $subject->pilot_fee_waiver_starts_at;
        $expiresAt = $subject->pilot_fee_waiver_expires_at;

        $starts = ! $startsAt instanceof CarbonInterface || $startsAt->lessThanOrEqualTo($at);
        $expires = $expiresAt instanceof CarbonInterface && $expiresAt->greaterThanOrEqualTo($at);

        return [
            'eligible' => $program->allowsNewWaivers()
                && (bool) $subject->pilot_fee_waiver_enabled
                && $starts
                && $expires,
            'program_status' => (string) $program->status,
            'starts_at' => $startsAt instanceof CarbonInterface ? $startsAt->toIso8601String() : null,
            'expires_at' => $expiresAt instanceof CarbonInterface ? $expiresAt->toIso8601String() : null,
        ];
    }

    /**
     * @param  array{enabled:bool, starts_at:?string, expires_at:?string, reason:?string}  $attributes
     */
    public function updateClient(Client $client, array $attributes, User $actor): Client
    {
        $this->updateSubject($client, $attributes, $actor);

        return $client->refresh();
    }

    /**
     * @param  array{enabled:bool, starts_at:?string, expires_at:?string, reason:?string}  $attributes
     */
    public function updateEntrepreneur(
        EntrepreneurProfile $profile,
        array $attributes,
        User $actor,
    ): EntrepreneurProfile {
        $this->updateSubject($profile, $attributes, $actor);

        return $profile->refresh();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array{eligible:bool, program_status:string, starts_at:?string, expires_at:?string}  $pilot
     * @return array<string, mixed>
     */
    public function waivedPackageSnapshot(array $snapshot, array $pilot): array
    {
        return [
            ...$snapshot,
            'fixed_fee' => 0.0,
            'deposit_percent' => 100.0,
            'payment_split' => [
                'deposit_percent' => 100.0,
                'card_deposit_amount' => 0.0,
                'bank_transfer_amount' => 0.0,
                'requires_bank_transfer' => false,
            ],
            'pilot_fee_waiver' => [
                'active' => true,
                'reason' => 'Pilot fee waiver active; package payment is not required for this client.',
                'nominal_fixed_fee' => $snapshot['fixed_fee'] ?? null,
                'program_status' => $pilot['program_status'],
                'starts_at' => $pilot['starts_at'],
                'expires_at' => $pilot['expires_at'],
                'stripe_required' => false,
            ],
        ];
    }

    /**
     * @param  array{enabled:bool, starts_at:?string, expires_at:?string, reason:?string}  $attributes
     */
    private function updateSubject(
        Client|EntrepreneurProfile $subject,
        array $attributes,
        User $actor,
    ): void {
        $enabled = $attributes['enabled'];

        $subject->forceFill([
            'pilot_fee_waiver_enabled' => $enabled,
            'pilot_fee_waiver_starts_at' => $enabled && filled($attributes['starts_at'])
                ? Carbon::parse($attributes['starts_at'])
                : null,
            'pilot_fee_waiver_expires_at' => $enabled && filled($attributes['expires_at'])
                ? Carbon::parse($attributes['expires_at'])->endOfDay()
                : null,
            'pilot_fee_waiver_reason' => $enabled ? $attributes['reason'] : null,
            'pilot_fee_waiver_approved_by_user_id' => $enabled ? $actor->getKey() : null,
            'pilot_fee_waiver_approved_at' => $enabled ? now() : null,
        ])->save();
    }
}
