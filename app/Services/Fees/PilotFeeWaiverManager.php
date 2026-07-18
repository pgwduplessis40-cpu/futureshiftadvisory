<?php

declare(strict_types=1);

namespace App\Services\Fees;

use App\Models\Client;
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
    public function eligibility(Client $client, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $program = $this->currentProgram();
        $startsAt = $client->pilot_fee_waiver_starts_at;
        $expiresAt = $client->pilot_fee_waiver_expires_at;

        $starts = ! $startsAt instanceof CarbonInterface || $startsAt->lessThanOrEqualTo($at);
        $expires = $expiresAt instanceof CarbonInterface && $expiresAt->greaterThanOrEqualTo($at);

        return [
            'eligible' => $program->allowsNewWaivers()
                && (bool) $client->pilot_fee_waiver_enabled
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
        $enabled = $attributes['enabled'];

        $client->forceFill([
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

        return $client->refresh();
    }
}
