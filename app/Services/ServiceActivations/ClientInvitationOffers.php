<?php

declare(strict_types=1);

namespace App\Services\ServiceActivations;

use App\Models\Client;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClientInvitationOffers
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly ServiceActivationPackagePricing $pricing,
    ) {}

    /**
     * Create the exact service offer selected by an advisor before a client
     * accepts an invitation.  This deliberately reuses the established
     * package/payment/acceptance lifecycle: an invitation is not a payment or
     * an activated workspace.
     */
    public function offerFromClientInvite(
        Client $client,
        User $advisor,
        ServiceRatePackage $package,
        string $inviteTokenId,
    ): ServiceActivation {
        if (! in_array($package->service_type, [
            ServiceActivation::SERVICE_DUE_DILIGENCE,
            ServiceActivation::SERVICE_DD_PLAN_BUDGET,
        ], true) || ! $package->is_active) {
            throw ValidationException::withMessages([
                'service_rate_package_id' => 'Choose an active DD or Business Plan & Budget package for this invitation.',
            ]);
        }

        $now = now();
        if ($package->effective_from !== null && $package->effective_from->greaterThan($now)) {
            throw ValidationException::withMessages([
                'service_rate_package_id' => 'The selected package is not effective yet.',
            ]);
        }
        if ($package->effective_to !== null && $package->effective_to->lessThanOrEqualTo($now)) {
            throw ValidationException::withMessages([
                'service_rate_package_id' => 'The selected package is no longer effective.',
            ]);
        }

        return DB::transaction(function () use ($advisor, $client, $inviteTokenId, $package): ServiceActivation {
            $existing = ServiceActivation::query()
                ->where('client_id', $client->getKey())
                ->where('service_type', $package->service_type)
                ->where('metadata->source', 'client_invite_offer')
                ->where('metadata->invite_token_id', $inviteTokenId)
                ->latest()
                ->first();

            if ($existing instanceof ServiceActivation) {
                return $existing;
            }

            $snapshot = $this->pricing->packageSnapshotForActivation($package, $client);
            /** @var ServiceActivation $activation */
            $activation = ServiceActivation::query()->create([
                'client_id' => $client->getKey(),
                'requested_by_user_id' => $advisor->getKey(),
                'advisor_id' => $advisor->getKey(),
                'approved_by_user_id' => $advisor->getKey(),
                'service_rate_package_id' => $package->getKey(),
                'service_type' => $package->service_type,
                'client_label' => $package->service_type === ServiceActivation::SERVICE_DD_PLAN_BUDGET
                    ? 'Business Plan & Budget add-on'
                    : 'Explore buying a business',
                'status' => ServiceActivation::STATUS_PACKAGE_SELECTED,
                'selected_package_snapshot' => $snapshot,
                'payment_status' => $this->pricing->packagePaymentStatus($snapshot),
                'metadata' => [
                    'source' => 'client_invite_offer',
                    'invite_token_id' => $inviteTokenId,
                    'package_selected_at' => now()->toIso8601String(),
                    'offered_at' => now()->toIso8601String(),
                    'payment_required_before_workspace_access' => $this->pricing->packagePaymentStatus($snapshot) !== ServiceActivation::PAYMENT_NOT_REQUIRED,
                    'offer_acknowledged_at' => null,
                ],
            ]);

            $this->audit->record('service_activation.invite_offer_created', subject: $activation, actor: $advisor, after: [
                'client_id' => $client->getKey(),
                'invite_token_id' => $inviteTokenId,
                'service_type' => $package->service_type,
                'service_rate_package_id' => $package->getKey(),
                'fixed_fee' => $snapshot['fixed_fee'] ?? null,
                'currency' => $snapshot['currency'],
            ]);

            return $activation->refresh();
        });
    }

    /**
     * @return Collection<int, ServiceActivation>
     */
    public function invitationOffers(Client $client): Collection
    {
        return ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('metadata->source', 'client_invite_offer')
            ->whereIn('status', [
                ServiceActivation::STATUS_PACKAGE_SELECTED,
                ServiceActivation::STATUS_ACTIVE,
            ])
            ->orderByRaw("case service_type when 'due_diligence' then 0 when 'dd_plan_budget' then 1 else 2 end")
            ->latest()
            ->get();
    }

    /**
     * Record the client's acknowledgement of the invitation's fixed package,
     * scope and fee during onboarding.  Workspace access remains protected by
     * the existing payment and activation checks.
     *
     * @return Collection<int, ServiceActivation>
     */
    public function acknowledgeInvitationOffers(Client $client, User $actor): Collection
    {
        $this->assertClientUser($client, $actor);

        return DB::transaction(function () use ($actor, $client): Collection {
            $offers = $this->invitationOffers($client)
                ->filter(fn (ServiceActivation $activation): bool => $activation->status === ServiceActivation::STATUS_PACKAGE_SELECTED);

            $acknowledgedAt = now();
            foreach ($offers as $offer) {
                $metadata = (array) ($offer->metadata ?? []);
                $offer->forceFill([
                    'metadata' => [
                        ...$metadata,
                        'offer_acknowledged_at' => $acknowledgedAt->toIso8601String(),
                        'offer_acknowledged_by_user_id' => $actor->getKey(),
                    ],
                ])->save();

                $this->audit->record('service_activation.invite_offer_acknowledged', subject: $offer, actor: $actor, after: [
                    'client_id' => $client->getKey(),
                    'service_type' => $offer->service_type,
                    'service_rate_package_id' => $offer->service_rate_package_id,
                    'acknowledged_at' => $acknowledgedAt->toIso8601String(),
                ]);
            }

            return $offers->map(fn (ServiceActivation $offer): ServiceActivation => $offer->refresh());
        });
    }

    public function rebindInvitationOffers(Client $client, string $oldInviteTokenId, string $newInviteTokenId): void
    {
        ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('metadata->source', 'client_invite_offer')
            ->where('metadata->invite_token_id', $oldInviteTokenId)
            ->where('status', ServiceActivation::STATUS_PACKAGE_SELECTED)
            ->get()
            ->each(function (ServiceActivation $offer) use ($newInviteTokenId): void {
                $offer->forceFill([
                    'metadata' => [
                        ...(array) ($offer->metadata ?? []),
                        'invite_token_id' => $newInviteTokenId,
                        'invite_resent_at' => now()->toIso8601String(),
                    ],
                ])->save();
            });
    }

    private function assertClientUser(Client $client, User $user): void
    {
        if (! in_array((string) $client->getKey(), $user->accessibleClientIds(), true)) {
            throw ValidationException::withMessages(['activation' => 'This workspace is not assigned to your client portal.']);
        }
    }
}
