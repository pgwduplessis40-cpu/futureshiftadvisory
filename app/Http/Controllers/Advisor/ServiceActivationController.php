<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\ServiceActivations\ServiceActivationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceActivationController extends Controller
{
    public function __construct(private readonly ServiceActivationManager $activations) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $clientIds = $user->user_type === User::TYPE_SUPER_ADMIN ? null : $user->accessibleClientIds();
        $query = ServiceActivation::query()
            ->with('client', 'advisor', 'package')
            ->latest();

        if (is_array($clientIds)) {
            $clientIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('client_id', $clientIds);
        }

        return Inertia::render('advisor/service-activations/Index', [
            'activations' => $query
                ->limit(100)
                ->get()
                ->map(fn (ServiceActivation $activation): array => $this->activationSummary($activation))
                ->values(),
        ]);
    }

    public function show(Request $request, ServiceActivation $serviceActivation): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $this->assertAdvisorCanView($serviceActivation, $user);

        return Inertia::render('advisor/service-activations/Show', [
            'activation' => $this->activationDetail($serviceActivation->refresh()->load('client', 'advisor', 'package')),
            'packages' => collect($this->activations->activePackagesFor($serviceActivation->service_type))
                ->map(fn (ServiceRatePackage $package): array => $this->packagePayload($package))
                ->values(),
            'urls' => [
                'index' => route('advisor.service-activations.index', absolute: false),
                'package' => route('advisor.service-activations.package', $serviceActivation, absolute: false),
                'balanceReceived' => route('advisor.service-activations.balance-received', $serviceActivation, absolute: false),
                'client' => route('advisor.clients.show', $serviceActivation->client_id, absolute: false),
            ],
        ]);
    }

    public function package(Request $request, ServiceActivation $serviceActivation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $this->assertAdvisorCanView($serviceActivation, $user);

        $validated = $request->validate([
            'service_rate_package_id' => ['required', 'string', 'uuid'],
        ]);

        $package = ServiceRatePackage::query()
            ->whereKey($validated['service_rate_package_id'])
            ->firstOrFail();

        $this->activations->selectPackage($serviceActivation, $package, $user);

        return to_route('advisor.service-activations.show', $serviceActivation)
            ->with('status', 'service-activation-package-selected');
    }

    public function balanceReceived(Request $request, ServiceActivation $serviceActivation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $this->assertAdvisorCanView($serviceActivation, $user);

        $this->activations->confirmBalanceReceived($serviceActivation, $user);

        return to_route('advisor.service-activations.show', $serviceActivation)
            ->with('status', 'service-activation-balance-received');
    }

    private function assertAdvisorCanView(ServiceActivation $activation, User $user): void
    {
        if ($user->user_type === User::TYPE_SUPER_ADMIN) {
            return;
        }

        abort_unless(
            in_array((string) $activation->client_id, $user->accessibleClientIds(), true)
            && $user->can(Permission::CLIENTS_VIEW->value),
            404,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function activationSummary(ServiceActivation $activation): array
    {
        return [
            'id' => $activation->id,
            'client_name' => $activation->client?->legal_name,
            'service_type' => $activation->service_type,
            'client_label' => $activation->clientLabel(),
            'status' => $activation->status,
            'status_label' => str($activation->status)->replace('_', ' ')->title()->toString(),
            'advisor_name' => $activation->advisor?->name,
            'package_label' => $activation->package?->client_label ?? data_get($activation->selected_package_snapshot, 'client_label'),
            'requested_at' => $activation->created_at?->toIso8601String(),
            'url' => route('advisor.service-activations.show', $activation, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activationDetail(ServiceActivation $activation): array
    {
        return [
            ...$this->activationSummary($activation),
            'client_id' => $activation->client_id,
            'intake' => $activation->intake ?? [],
            'package' => is_array($activation->selected_package_snapshot)
                ? $activation->selected_package_snapshot
                : null,
            'payment_status' => $activation->payment_status ?? ServiceActivation::PAYMENT_NOT_REQUIRED,
            'payment_status_label' => str((string) ($activation->payment_status ?? ServiceActivation::PAYMENT_NOT_REQUIRED))->replace('_', ' ')->title()->toString(),
            'payment_completed_at' => $activation->payment_completed_at?->toIso8601String(),
            'deposit_paid_at' => $activation->deposit_paid_at?->toIso8601String(),
            'deposit_reference' => $activation->deposit_reference,
            'balance_received_at' => $activation->balance_received_at?->toIso8601String(),
            'balance_reference' => $activation->balance_reference,
            'accepted_at' => $activation->accepted_at?->toIso8601String(),
            'workspace' => [
                'dd_engagement_id' => $activation->related_dd_engagement_id,
                'entrepreneur_profile_id' => $activation->related_entrepreneur_profile_id,
            ],
            'message_thread_id' => $activation->client_message_thread_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packagePayload(ServiceRatePackage $package): array
    {
        return [
            ...$package->snapshot(),
            'is_active' => $package->is_active,
        ];
    }
}
