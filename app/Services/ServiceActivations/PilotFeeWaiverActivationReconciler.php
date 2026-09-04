<?php

declare(strict_types=1);

namespace App\Services\ServiceActivations;

use App\Models\Client;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Fees\PilotFeeWaiverManager;
use Illuminate\Support\Facades\DB;

final class PilotFeeWaiverActivationReconciler
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly PilotFeeWaiverManager $pilotWaivers,
    ) {}

    public function applyIfEligible(ServiceActivation $activation, ?User $actor = null): ServiceActivation
    {
        return DB::transaction(function () use ($activation, $actor): ServiceActivation {
            $activation = ServiceActivation::query()
                ->lockForUpdate()
                ->findOrFail($activation->getKey());
            $activation->load('client');
            $client = $activation->client;

            if (! $client instanceof Client || ! is_array($activation->selected_package_snapshot) || $activation->paymentComplete()) {
                return $activation->refresh();
            }

            $pilot = $this->pilotWaivers->eligibility($client);
            if (! $pilot['eligible']) {
                return $activation->refresh();
            }

            $before = [
                'payment_status' => $activation->payment_status,
                'fixed_fee' => data_get($activation->selected_package_snapshot, 'fixed_fee'),
                'deposit_percent' => data_get($activation->selected_package_snapshot, 'deposit_percent'),
            ];
            $snapshot = $this->pilotWaivers->waivedPackageSnapshot($activation->selected_package_snapshot, $pilot);

            $activation->forceFill([
                'selected_package_snapshot' => $snapshot,
                'payment_status' => ServiceActivation::PAYMENT_NOT_REQUIRED,
                'metadata' => [
                    ...(array) ($activation->metadata ?? []),
                    'pilot_fee_waiver' => true,
                    'payment_required_before_workspace_access' => false,
                    'pilot_fee_waiver_applied_at' => now()->toIso8601String(),
                ],
            ])->save();

            $this->audit->record('service_activation.pilot_fee_waiver_applied', subject: $activation, actor: $actor, before: $before, after: [
                'payment_status' => ServiceActivation::PAYMENT_NOT_REQUIRED,
                'nominal_fixed_fee' => data_get($snapshot, 'pilot_fee_waiver.nominal_fixed_fee'),
                'pilot_waiver_expires_at' => data_get($snapshot, 'pilot_fee_waiver.expires_at'),
            ]);

            return $activation->refresh();
        });
    }

    public function reconcileForClient(Client $client, ?User $actor = null): int
    {
        return ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('status', ServiceActivation::STATUS_PACKAGE_SELECTED)
            ->whereIn('payment_status', [
                ServiceActivation::PAYMENT_PENDING,
                ServiceActivation::PAYMENT_DEPOSIT_PENDING,
                ServiceActivation::PAYMENT_BALANCE_PENDING,
            ])
            ->whereNotNull('selected_package_snapshot')
            ->orderBy('id')
            ->get()
            ->reduce(function (int $reconciled, ServiceActivation $activation) use ($actor): int {
                return $this->applyIfEligible($activation, $actor)->payment_status === ServiceActivation::PAYMENT_NOT_REQUIRED
                    ? $reconciled + 1
                    : $reconciled;
            }, 0);
    }
}
