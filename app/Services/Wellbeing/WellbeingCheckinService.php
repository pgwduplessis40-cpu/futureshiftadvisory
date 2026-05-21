<?php

declare(strict_types=1);

namespace App\Services\Wellbeing;

use App\Models\Client;
use App\Models\CoachingSignal;
use App\Models\User;
use App\Models\WellbeingCheckin;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WellbeingCheckinService
{
    public function __construct(
        private readonly AuditWriter $auditWriter,
        private readonly CoachingSignalDetector $signals,
        private readonly RequestContext $context,
    ) {}

    /**
     * @param  array{business_confidence:int, personal_coping:int, notes?:string|null}  $data
     */
    public function record(Client $client, User $user, array $data): WellbeingCheckin
    {
        $periodStart = now()->startOfMonth()->toDateString();

        return DB::transaction(function () use ($client, $data, $periodStart, $user): WellbeingCheckin {
            $checkin = WellbeingCheckin::query()->updateOrCreate(
                [
                    'client_id' => $client->getKey(),
                    'user_id' => $user->getKey(),
                    'period_start' => $periodStart,
                ],
                [
                    'business_confidence' => $data['business_confidence'],
                    'personal_coping' => $data['personal_coping'],
                    'notes' => $data['notes'] ?? null,
                    'submitted_at' => now(),
                ],
            );

            $this->auditWriter->record('wellbeing.submitted', subject: $checkin, actor: $user, after: [
                'client_id' => $client->getKey(),
                'period_start' => $periodStart,
                'business_confidence' => $data['business_confidence'],
                'personal_coping' => $data['personal_coping'],
                'notes_present' => filled($data['notes'] ?? null),
            ]);

            $this->signals->evaluate($checkin->refresh());

            return $checkin;
        });
    }

    public function delete(WellbeingCheckin $checkin, User $user): void
    {
        if (! $checkin->canBeDeletedBy($user)) {
            throw ValidationException::withMessages([
                'checkin' => 'Check-ins can only be deleted by the submitter within 7 days.',
            ]);
        }

        DB::transaction(function () use ($checkin, $user): void {
            $this->auditWriter->record('wellbeing.deleted', subject: $checkin, actor: $user, before: [
                'client_id' => $checkin->client_id,
                'period_start' => $checkin->period_start?->toDateString(),
                'business_confidence' => $checkin->business_confidence,
                'personal_coping' => $checkin->personal_coping,
                'notes_present' => filled($checkin->notes),
            ]);

            $this->context->apply('system', []);

            CoachingSignal::query()
                ->where('trigger_checkin_id', $checkin->getKey())
                ->delete();

            $checkin->delete();
        });
    }
}
