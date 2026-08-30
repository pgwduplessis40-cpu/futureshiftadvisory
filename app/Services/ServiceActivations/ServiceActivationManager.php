<?php

declare(strict_types=1);

namespace App\Services\ServiceActivations;

use App\Enums\EntrepreneurStage;
use App\Enums\FeeMethod;
use App\Models\BillingAdjustment;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ConflictDeclaration;
use App\Models\DdEngagement;
use App\Models\EntrepreneurProfile;
use App\Models\IntegrationScope;
use App\Models\LearningUpdate;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Proposal;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Notifications\ServiceActivationRequestedNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Dd\ClientCapability;
use App\Services\Fees\PilotFeeWaiverManager;
use App\Services\Fees\ServiceRateManager;
use App\Services\Goals\GoalTracker;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\Messaging\MessageThreadService;
use App\Services\Plans\PlanBuilder as SharedPlanBuilder;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ServiceActivationManager
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly MessageThreadService $messages,
        private readonly SharedPlanBuilder $plans,
        private readonly RequestContext $context,
        private readonly ServiceRateManager $serviceRates,
        private readonly PilotFeeWaiverManager $pilotWaivers,
        private readonly GoalTracker $goals,
        private readonly ClientCapability $ddClientCapability,
    ) {}

    public function offerIntegrationScoping(Client $client, User $advisor, ServiceRatePackage $package): ServiceActivation
    {
        if (! in_array($advisor->user_type, [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR, User::TYPE_SUPER_ADMIN], true)) {
            throw ValidationException::withMessages(['advisor' => 'Only an advisor can offer integration scoping.']);
        }
        if ($package->service_type !== ServiceActivation::SERVICE_INTEGRATION_SCOPING || ! $package->is_active) {
            throw ValidationException::withMessages(['service_rate_package_id' => 'Choose an active integration-scoping package.']);
        }

        return DB::transaction(function () use ($client, $advisor, $package): ServiceActivation {
            $this->assertNoBlockingOpenActivation($client, ServiceActivation::SERVICE_INTEGRATION_SCOPING);
            $snapshot = $this->packageSnapshotForActivation($package, $client);
            $activation = ServiceActivation::query()->create([
                'client_id' => $client->getKey(),
                'requested_by_user_id' => $advisor->getKey(),
                'advisor_id' => $advisor->getKey(),
                'approved_by_user_id' => $advisor->getKey(),
                'service_type' => ServiceActivation::SERVICE_INTEGRATION_SCOPING,
                'client_label' => 'Systems integration scoping',
                'service_rate_package_id' => $package->getKey(),
                'selected_package_snapshot' => $snapshot,
                'payment_status' => $this->packagePaymentStatus($snapshot),
                'status' => ServiceActivation::STATUS_PACKAGE_SELECTED,
                'metadata' => [
                    'source' => 'advisor_offer',
                    'package_selected_at' => now()->toIso8601String(),
                    'payment_required_before_scope_starts' => true,
                ],
            ]);
            $this->audit->record('integration.scoping_offered', subject: $activation, actor: $advisor, after: [
                'client_id' => $client->getKey(),
                'service_rate_package_id' => $package->getKey(),
                'fixed_fee' => $snapshot['fixed_fee'] ?? null,
            ]);

            return $activation->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $intake
     */
    public function request(
        Client $client,
        User $actor,
        string $serviceType,
        array $intake,
        ?array $pricingPreview = null,
    ): ServiceActivation {
        $serviceType = $this->normaliseServiceType($serviceType);
        $this->assertNoBlockingOpenActivation($client, $serviceType);
        $advisor = $this->leadAdvisor($client);
        $pricingPreview ??= $this->pricingPreviewForRequest($serviceType, $intake, client: $client);

        $activation = DB::transaction(function () use ($client, $actor, $serviceType, $intake, $advisor, $pricingPreview): ServiceActivation {
            $activation = ServiceActivation::query()->create([
                'client_id' => $client->getKey(),
                'requested_by_user_id' => $actor->getKey(),
                'advisor_id' => $advisor?->getKey(),
                'service_type' => $serviceType,
                'client_label' => $this->clientLabel($serviceType),
                'status' => ServiceActivation::STATUS_REQUESTED,
                'intake' => $this->cleanIntake($serviceType, $intake),
                'metadata' => [
                    'source' => 'client_self_start',
                    'opportunity_type' => 'sales_opportunity',
                    'internal_service_type' => $serviceType,
                    'pre_request_pricing' => $this->storedPricingPreview($pricingPreview),
                    'pricing_acknowledged_at' => now()->toIso8601String(),
                    'pricing_acknowledged_by_user_id' => $actor->getKey(),
                ],
            ]);

            $message = $this->startRequestThread($activation->refresh(), $actor);
            if ($message instanceof Message && $message->thread !== null) {
                $activation->forceFill(['client_message_thread_id' => $message->thread->getKey()])->save();
            }

            $this->audit->record('service_activation.requested', subject: $activation, actor: $actor, after: [
                'client_id' => $client->getKey(),
                'service_type' => $serviceType,
                'advisor_id' => $advisor?->getKey(),
                'pre_request_pricing_status' => $pricingPreview['status'] ?? null,
                'pre_request_package_id' => data_get($pricingPreview, 'package.id'),
                'pre_request_combined_fixed_fee' => data_get($pricingPreview, 'package.quote_context.combined_fixed_fee'),
            ]);

            return $activation->refresh();
        });

        $this->notifyAdvisorOfRequest($activation, $advisor);

        $this->queueLearningSafely($activation, 'requested', [
            'status' => $activation->status,
            'advisor_assigned' => $advisor instanceof User,
        ]);

        return $activation;
    }

    public function selectPackage(ServiceActivation $activation, ServiceRatePackage $package, User $advisor): ServiceActivation
    {
        $activation->loadMissing('client');
        $snapshot = $this->packageSnapshotForActivation($package, $activation->client);
        if ($activation->service_type === ServiceActivation::SERVICE_DD_PLAN_BUDGET) {
            $snapshot = $this->withDdPlanBudgetQuoteContext(
                $snapshot,
                $this->matchedDueDiligencePackageSnapshot($activation->client, (array) ($activation->intake ?? [])),
            );
        }
        $paymentStatus = $this->packagePaymentStatus($snapshot);

        if (! in_array($advisor->user_type, [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR, User::TYPE_SUPER_ADMIN], true)) {
            throw ValidationException::withMessages(['advisor' => 'Only an advisor can select the workspace package.']);
        }

        if ((string) $package->service_type !== (string) $activation->service_type || ! $package->is_active) {
            throw ValidationException::withMessages(['service_rate_package_id' => 'Select an active package that matches the requested service.']);
        }

        $activation->forceFill([
            'advisor_id' => $activation->advisor_id ?: $advisor->getKey(),
            'approved_by_user_id' => $advisor->getKey(),
            'service_rate_package_id' => $package->getKey(),
            'selected_package_snapshot' => $snapshot,
            'payment_status' => $paymentStatus,
            'payment_completed_at' => null,
            'payment_completed_by_user_id' => null,
            'payment_reference' => null,
            'deposit_paid_at' => null,
            'deposit_paid_by_user_id' => null,
            'deposit_reference' => null,
            'balance_received_at' => null,
            'balance_received_by_user_id' => null,
            'balance_reference' => null,
            'status' => ServiceActivation::STATUS_PACKAGE_SELECTED,
            'metadata' => [
                ...(array) ($activation->metadata ?? []),
                'package_selected_at' => now()->toIso8601String(),
                'pricing_source' => 'admin_service_rate_package',
                'free_access_mode' => (bool) data_get($snapshot, 'free_access_mode.active', false),
                'pilot_fee_waiver' => (bool) data_get($snapshot, 'pilot_fee_waiver.active', false),
                'payment_required_before_workspace_access' => $paymentStatus !== ServiceActivation::PAYMENT_NOT_REQUIRED,
            ],
        ])->save();

        $this->audit->record('service_activation.package_selected', subject: $activation, actor: $advisor, after: [
            'service_rate_package_id' => $package->getKey(),
            'service_type' => $activation->service_type,
            'fixed_fee' => $snapshot['fixed_fee'] ?? null,
            'payment_split' => $snapshot['payment_split'] ?? null,
            'quote_context' => $snapshot['quote_context'] ?? null,
            'billing_model' => $package->billing_model,
        ]);

        $this->queueLearningSafely($activation->refresh(), 'package_selected', [
            'package_id' => $package->getKey(),
            'billing_model' => $package->billing_model,
        ]);

        return $activation->refresh();
    }

    public function completePayment(ServiceActivation $activation, User $actor): ServiceActivation
    {
        $activation->loadMissing('client');
        $client = $activation->client;

        if (! $client instanceof Client) {
            throw ValidationException::withMessages(['activation' => 'The activation is not linked to a client.']);
        }

        $this->assertClientUser($client, $actor);

        if ($activation->status !== ServiceActivation::STATUS_PACKAGE_SELECTED || ! is_array($activation->selected_package_snapshot)) {
            throw ValidationException::withMessages(['activation' => 'The advisor must select the package before payment can be completed.']);
        }

        $activation = $this->applyPilotFeeWaiverIfEligible($activation, $actor);
        if ($activation->paymentComplete()) {
            return $activation->refresh();
        }

        if (! $this->activationRequiresPayment($activation)) {
            $activation->forceFill([
                'payment_status' => ServiceActivation::PAYMENT_NOT_REQUIRED,
                'payment_completed_at' => null,
                'payment_completed_by_user_id' => null,
                'payment_reference' => null,
                'deposit_paid_at' => null,
                'deposit_paid_by_user_id' => null,
                'deposit_reference' => null,
                'balance_received_at' => null,
                'balance_received_by_user_id' => null,
                'balance_reference' => null,
            ])->save();

            return $activation->refresh();
        }

        if ($activation->payment_status === ServiceActivation::PAYMENT_BALANCE_PENDING) {
            throw ValidationException::withMessages([
                'payment' => 'The card deposit has already been paid. The bank-transfer balance must be received and confirmed before workspace access opens.',
            ]);
        }

        if ($activation->paymentComplete()) {
            return $activation->refresh();
        }

        if (! $this->testPaymentCompletionAllowed()) {
            throw ValidationException::withMessages([
                'payment' => 'Activation package payments must be completed through the configured payment provider.',
            ]);
        }

        $split = $this->paymentSplitForSnapshot((array) $activation->selected_package_snapshot);
        $reference = 'activation-card-'.$activation->getKey().'-'.now()->format('YmdHis');
        $now = now();
        $requiresBankTransfer = (bool) $split['requires_bank_transfer'];

        $activation->forceFill([
            'payment_status' => $requiresBankTransfer
                ? ServiceActivation::PAYMENT_BALANCE_PENDING
                : ServiceActivation::PAYMENT_PAID,
            'deposit_paid_at' => $now,
            'deposit_paid_by_user_id' => $actor->getKey(),
            'deposit_reference' => $reference,
            'payment_completed_at' => $requiresBankTransfer ? null : $now,
            'payment_completed_by_user_id' => $requiresBankTransfer ? null : $actor->getKey(),
            'payment_reference' => $requiresBankTransfer ? null : $reference,
            'metadata' => [
                ...(array) ($activation->metadata ?? []),
                'deposit_paid_at' => $now->toIso8601String(),
                'payment_mode' => $requiresBankTransfer
                    ? 'test_environment_card_deposit'
                    : 'test_environment_card_full_payment',
                'balance_required_before_workspace_access' => $requiresBankTransfer,
            ],
        ])->save();

        $this->audit->record('service_activation.card_payment_completed', subject: $activation, actor: $actor, after: [
            'service_type' => $activation->service_type,
            'service_rate_package_id' => $activation->service_rate_package_id,
            'payment_reference' => $reference,
            'payment_status' => $activation->payment_status,
            'payment_split' => $split,
        ]);

        $this->queueLearningSafely($activation->refresh(), 'payment_completed', [
            'package_snapshot' => $activation->selected_package_snapshot,
            'payment_reference' => $reference,
        ]);

        if ($activation->service_type === ServiceActivation::SERVICE_INTEGRATION_SCOPING && ! $requiresBankTransfer) {
            return $this->activateScopingFromPackagePayment($activation->refresh(), $actor);
        }

        return $activation->refresh();
    }

    public function confirmBalanceReceived(ServiceActivation $activation, User $actor): ServiceActivation
    {
        $activation->loadMissing('client');

        if (! in_array($actor->user_type, [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR, User::TYPE_SUPER_ADMIN], true)) {
            throw ValidationException::withMessages(['advisor' => 'Only an advisor can confirm the bank-transfer balance.']);
        }

        if ($activation->status !== ServiceActivation::STATUS_PACKAGE_SELECTED || ! is_array($activation->selected_package_snapshot)) {
            throw ValidationException::withMessages(['activation' => 'The package must be selected before the bank-transfer balance can be confirmed.']);
        }

        $split = $this->paymentSplitForSnapshot((array) $activation->selected_package_snapshot);

        if (! (bool) $split['requires_bank_transfer']) {
            throw ValidationException::withMessages(['payment' => 'This package does not require a bank-transfer balance.']);
        }

        if ($activation->deposit_paid_at === null || $activation->payment_status !== ServiceActivation::PAYMENT_BALANCE_PENDING) {
            throw ValidationException::withMessages(['payment' => 'The card deposit must be paid before confirming the bank-transfer balance.']);
        }

        $reference = 'activation-balance-'.$activation->getKey().'-'.now()->format('YmdHis');
        $now = now();

        $activation->forceFill([
            'payment_status' => ServiceActivation::PAYMENT_PAID,
            'balance_received_at' => $now,
            'balance_received_by_user_id' => $actor->getKey(),
            'balance_reference' => $reference,
            'payment_completed_at' => $now,
            'payment_completed_by_user_id' => $actor->getKey(),
            'payment_reference' => $reference,
            'metadata' => [
                ...(array) ($activation->metadata ?? []),
                'balance_received_at' => $now->toIso8601String(),
                'payment_completed_at' => $now->toIso8601String(),
                'payment_mode' => 'test_environment_bank_transfer_balance_confirmed',
            ],
        ])->save();

        $this->audit->record('service_activation.balance_received', subject: $activation, actor: $actor, after: [
            'service_type' => $activation->service_type,
            'service_rate_package_id' => $activation->service_rate_package_id,
            'balance_reference' => $reference,
            'payment_status' => ServiceActivation::PAYMENT_PAID,
            'payment_split' => $split,
        ]);

        $this->queueLearningSafely($activation->refresh(), 'balance_received', [
            'package_snapshot' => $activation->selected_package_snapshot,
            'balance_reference' => $reference,
        ]);

        if ($activation->service_type === ServiceActivation::SERVICE_INTEGRATION_SCOPING) {
            return $this->activateScopingFromPackagePayment($activation->refresh(), $actor);
        }

        return $activation->refresh();
    }

    public function applyPilotFeeWaiverIfEligible(ServiceActivation $activation, ?User $actor = null): ServiceActivation
    {
        $activation->loadMissing('client');
        $client = $activation->client;

        if (! $client instanceof Client || ! is_array($activation->selected_package_snapshot)) {
            return $activation->refresh();
        }

        if ($activation->paymentComplete()) {
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
        $snapshot = $this->pilotWaivedPackageSnapshot((array) $activation->selected_package_snapshot, $pilot);

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
    }

    public function activateIntegrationFromProposalPayment(Proposal $proposal, ?Payment $payment = null): ServiceActivation
    {
        $proposal->loadMissing(['client', 'feeCalculation.integrationScope']);
        $scope = $proposal->feeCalculation?->integrationScope;
        if ($proposal->feeCalculation?->method !== FeeMethod::Integration || ! $scope instanceof IntegrationScope) {
            throw new \InvalidArgumentException('Only an integration proposal can activate the integration delivery service.');
        }
        if ((string) $scope->client_id !== (string) $proposal->client_id) {
            throw new \InvalidArgumentException('The integration scope must belong to the proposal client.');
        }

        return DB::transaction(function () use ($proposal, $scope, $payment): ServiceActivation {
            $existing = ServiceActivation::query()->where('proposal_id', $proposal->getKey())->lockForUpdate()->first();
            if ($existing instanceof ServiceActivation) {
                return $existing;
            }

            $activation = ServiceActivation::query()->create([
                'client_id' => $proposal->client_id,
                'advisor_id' => $proposal->created_by_user_id,
                'service_type' => ServiceActivation::SERVICE_INTEGRATION,
                'client_label' => 'Systems integration delivery',
                'proposal_id' => $proposal->getKey(),
                'status' => ServiceActivation::STATUS_ACTIVE,
                'payment_status' => ServiceActivation::PAYMENT_PAID,
                'payment_completed_at' => now(),
                'payment_reference' => $payment?->gateway_ref ?? ('proposal-payment-'.$proposal->getKey()),
                'accepted_at' => now(),
                'acceptance_text' => 'Signed proposal and settled installment consent evidence.',
                'terms_reference' => [
                    'source' => 'signed_proposal_payment',
                    'proposal_id' => $proposal->getKey(),
                    'payment_id' => $payment?->getKey(),
                ],
                'metadata' => ['workspace_type' => 'integration_delivery'],
            ]);

            if ($scope->goal_id === null) {
                $goal = $this->goals->createGoal($proposal->client, [
                    'title' => 'Reduce manual integration work',
                    'description' => 'Realise the verified savings captured in the approved systems-integration scope.',
                    'pv_target_calculation_id' => $scope->pv_calculation_id,
                    'target_date' => now()->addDays(90)->toDateString(),
                ]);
                $scope->forceFill(['goal_id' => $goal->getKey()])->save();
                foreach (['Confirm integration design', 'Build and test the approved connections', 'Measure post-launch time savings'] as $index => $title) {
                    $this->goals->createMilestone($goal, [
                        'title' => $title,
                        'recommendation_ref' => 'integration_scope:'.$scope->getKey(),
                        'pv_of_impact' => $index === 2 ? (float) data_get($scope->computed, 'pv_savings', 0) : 0,
                        'due_date' => now()->addDays(($index + 1) * 30)->toDateString(),
                    ]);
                }
            }

            $this->audit->record('integration.delivery_activated', subject: $activation, after: [
                'proposal_id' => $proposal->getKey(),
                'payment_id' => $payment?->getKey(),
                'integration_scope_id' => $scope->getKey(),
                'goal_id' => $scope->goal_id,
            ]);

            return $activation->refresh();
        });
    }

    private function activateScopingFromPackagePayment(ServiceActivation $activation, User $actor): ServiceActivation
    {
        return DB::transaction(function () use ($activation, $actor): ServiceActivation {
            $activation = ServiceActivation::query()->lockForUpdate()->findOrFail($activation->getKey());
            if ($activation->status === ServiceActivation::STATUS_ACTIVE) {
                return $activation;
            }
            if ($activation->service_type !== ServiceActivation::SERVICE_INTEGRATION_SCOPING || ! $activation->paymentComplete()) {
                throw ValidationException::withMessages(['activation' => 'Integration scoping must have a settled package payment.']);
            }

            $activation->forceFill([
                'status' => ServiceActivation::STATUS_ACTIVE,
                'accepted_by_user_id' => $actor->getKey(),
                'accepted_at' => now(),
                'acceptance_text' => 'Advisor-offered integration scoping package, with package payment completed.',
                'terms_reference' => ['source' => 'advisor_offer_package_payment', 'payment_reference' => $activation->payment_reference],
            ])->save();

            $credit = BillingAdjustment::query()->firstOrCreate(
                ['source_service_activation_id' => $activation->getKey()],
                [
                    'client_id' => $activation->client_id,
                    'type' => BillingAdjustment::TYPE_SCOPING_FEE_CREDIT,
                    'source_payment_reference' => $activation->payment_reference,
                    'amount' => (float) data_get($activation->selected_package_snapshot, 'fixed_fee', 0),
                    'currency' => (string) data_get($activation->selected_package_snapshot, 'currency', 'NZD'),
                    'status' => BillingAdjustment::STATUS_AVAILABLE,
                    'created_by_user_id' => $actor->getKey(),
                ],
            );
            $this->audit->record('integration.scoping_activated', subject: $activation, actor: $actor, after: [
                'billing_adjustment_id' => $credit->getKey(),
                'credit_amount' => $credit->amount,
            ]);

            return $activation->refresh();
        });
    }

    public function accept(ServiceActivation $activation, User $actor): ServiceActivation
    {
        $activation->loadMissing('client', 'package');
        $client = $activation->client;

        if (! $client instanceof Client) {
            throw ValidationException::withMessages(['activation' => 'The activation is not linked to a client.']);
        }

        if ($activation->status !== ServiceActivation::STATUS_PACKAGE_SELECTED || ! is_array($activation->selected_package_snapshot)) {
            throw ValidationException::withMessages(['activation' => 'The advisor must select the package and scope before you can accept.']);
        }

        if (! in_array($activation->service_type, [
            ServiceActivation::SERVICE_DUE_DILIGENCE,
            ServiceActivation::SERVICE_DD_PLAN_BUDGET,
            ServiceActivation::SERVICE_ENTREPRENEUR,
        ], true)) {
            throw ValidationException::withMessages([
                'activation' => 'This advisor-led service is activated by FSA through its dedicated scope and payment workflow.',
            ]);
        }

        $this->assertClientUser($client, $actor);

        $activation = $this->applyPilotFeeWaiverIfEligible($activation, $actor);

        if (! $activation->paymentComplete()) {
            throw ValidationException::withMessages(['payment' => 'Full package payment must be received before opening this workspace.']);
        }

        $acceptanceText = $this->acceptanceText($activation);

        $activation = DB::transaction(function () use ($activation, $actor, $acceptanceText): ServiceActivation {
            $activation->forceFill([
                'status' => ServiceActivation::STATUS_ACTIVE,
                'accepted_by_user_id' => $actor->getKey(),
                'accepted_at' => now(),
                'acceptance_text' => $acceptanceText,
                'terms_reference' => [
                    'standard_terms_already_accepted' => true,
                    'workspace_specific_fee_scope_acknowledged' => true,
                    'payment_status' => $activation->payment_status,
                    'payment_completed_at' => $activation->payment_completed_at?->toIso8601String(),
                    'accepted_at' => now()->toIso8601String(),
                ],
            ])->save();

            $this->context->withSystemContext(fn () => $this->ensureWorkspace($activation->refresh(), $actor));

            $this->audit->record('service_activation.accepted', subject: $activation, actor: $actor, after: [
                'service_type' => $activation->service_type,
                'service_rate_package_id' => $activation->service_rate_package_id,
                'accepted_at' => $activation->accepted_at?->toIso8601String(),
                'workspace_links' => [
                    'dd_engagement_id' => $activation->related_dd_engagement_id,
                    'entrepreneur_profile_id' => $activation->related_entrepreneur_profile_id,
                ],
            ]);

            return $activation->refresh();
        });

        $this->queueLearningSafely($activation, 'accepted', [
            'package_snapshot' => $activation->selected_package_snapshot,
            'workspace_created' => $activation->service_type !== ServiceActivation::SERVICE_DD_PLAN_BUDGET,
            'plan_budget_entitlement_activated' => $activation->service_type === ServiceActivation::SERVICE_DD_PLAN_BUDGET,
        ]);

        return $activation;
    }

    /**
     * @return array<int, ServiceRatePackage>
     */
    public function activePackagesFor(string $serviceType): array
    {
        return ServiceRatePackage::query()
            ->where('service_type', $this->normaliseServiceType($serviceType))
            ->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>', now());
            })
            ->orderBy('purchase_price_min')
            ->orderBy('fixed_fee')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $intake
     * @return array<string, mixed>
     */
    public function pricingPreviewForRequest(
        string $serviceType,
        array $intake = [],
        bool $includePackages = false,
        ?Client $client = null,
    ): array {
        $serviceType = $this->normaliseServiceType($serviceType);
        $packages = collect($this->activePackagesFor($serviceType));
        $packageSnapshots = $packages
            ->map(fn (ServiceRatePackage $package): array => $this->packageSnapshotForActivation($package, $client))
            ->values()
            ->all();

        if ($packages->isEmpty()) {
            return $this->pricingPreviewPayload(
                status: 'pricing_to_confirm',
                message: 'Pricing will be confirmed by your advisor before any charge or workspace access.',
                includePackages: $includePackages,
                packages: $packageSnapshots,
            );
        }

        if ($serviceType === ServiceActivation::SERVICE_DD_PLAN_BUDGET) {
            $addOnPackage = $packages->first();

            if (! $addOnPackage instanceof ServiceRatePackage) {
                return $this->pricingPreviewPayload(
                    status: 'pricing_to_confirm',
                    message: 'Pricing will be confirmed by your advisor before any charge or Business Plan & Budget access.',
                    includePackages: $includePackages,
                    packages: $packageSnapshots,
                );
            }

            $addOnSnapshot = $this->withDdPlanBudgetQuoteContext(
                $this->packageSnapshotForActivation($addOnPackage, $client),
                $this->matchedDueDiligencePackageSnapshot($client, $intake),
            );

            $combinedFee = data_get($addOnSnapshot, 'quote_context.combined_fixed_fee');

            return $this->pricingPreviewPayload(
                status: $combinedFee !== null ? 'matched_package' : 'pricing_to_confirm',
                message: $combinedFee !== null
                    ? 'FSA will combine the matched DD price band with the single Business Plan & Budget add-on fee before approval.'
                    : 'FSA will confirm the matched DD price band and add the Business Plan & Budget fee before any charge or access.',
                package: $addOnSnapshot,
                includePackages: $includePackages,
                packages: $packageSnapshots,
            );
        }

        if ($serviceType === ServiceActivation::SERVICE_DUE_DILIGENCE) {
            $askingPrice = $intake['asking_price'] ?? null;

            if (! is_numeric($askingPrice)) {
                return $this->pricingPreviewPayload(
                    status: 'needs_purchase_price',
                    message: 'Enter the asking price to show the matching package and GST-exclusive fee before requesting access.',
                    includePackages: $includePackages,
                    packages: $packageSnapshots,
                );
            }

            $matchedPackage = $packages->first(
                fn (ServiceRatePackage $package): bool => $this->packageMatchesPurchasePrice($package, (float) $askingPrice),
            );

            if (! $matchedPackage instanceof ServiceRatePackage) {
                return $this->pricingPreviewPayload(
                    status: 'pricing_to_confirm',
                    message: 'No active package exactly matches this asking price. Your advisor will confirm pricing before any charge or workspace access.',
                    includePackages: $includePackages,
                    packages: $packageSnapshots,
                );
            }

            $snapshot = $this->packageSnapshotForActivation($matchedPackage, $client);

            return $this->pricingPreviewPayload(
                status: 'matched_package',
                message: $this->packageRequiresPayment($snapshot)
                    ? 'This is the matched GST-exclusive package fee before you request access.'
                    : 'No payment will be requested for this service at this time.',
                package: $snapshot,
                includePackages: $includePackages,
                packages: $packageSnapshots,
            );
        }

        return $this->pricingPreviewPayload(
            status: 'pricing_to_confirm',
            message: 'Your advisor will confirm the appropriate package and GST-exclusive fee before any charge or workspace access.',
            includePackages: $includePackages,
            packages: $packageSnapshots,
        );
    }

    private function normaliseServiceType(string $serviceType): string
    {
        $serviceType = trim($serviceType);

        if (! in_array($serviceType, [ServiceActivation::SERVICE_DUE_DILIGENCE, ServiceActivation::SERVICE_DD_PLAN_BUDGET, ServiceActivation::SERVICE_ENTREPRENEUR, ServiceActivation::SERVICE_INTEGRATION_SCOPING, ServiceActivation::SERVICE_INTEGRATION], true)) {
            throw ValidationException::withMessages(['service_type' => 'Choose a supported workspace.']);
        }

        return $serviceType;
    }

    private function assertNoBlockingOpenActivation(Client $client, string $serviceType): void
    {
        $exists = ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('service_type', $serviceType)
            ->whereNotIn('status', [
                ServiceActivation::STATUS_CANCELLED,
                ServiceActivation::STATUS_CLOSED,
                ServiceActivation::STATUS_REJECTED,
            ])
            ->exists();

        if ($exists) {
            $message = match ($serviceType) {
                ServiceActivation::SERVICE_DUE_DILIGENCE => 'You already have an open buying-a-business workspace. Close or cancel it before starting another DD request.',
                ServiceActivation::SERVICE_DD_PLAN_BUDGET => 'You already have an open Business Plan & Budget quote or add-on for this DD workspace.',
                ServiceActivation::SERVICE_ENTREPRENEUR => 'You already have an open idea-testing workspace. Close or cancel it before starting another one.',
                default => 'You already have an open integration service for this stage. Close or cancel it before starting another one.',
            };

            throw ValidationException::withMessages(['service_type' => $message]);
        }
    }

    private function leadAdvisor(Client $client): ?User
    {
        $client->loadMissing('teamMembers.user');

        $member = $client->teamMembers
            ->first(fn (ClientTeamMember $teamMember): bool => $teamMember->role === 'lead_advisor'
                && $teamMember->user instanceof User
                && in_array($teamMember->user->user_type, [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN], true));

        if ($member?->user instanceof User) {
            return $member->user;
        }

        return User::query()
            ->whereIn('user_type', [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN])
            ->oldest()
            ->first();
    }

    private function assertClientUser(Client $client, User $user): void
    {
        if (! in_array((string) $client->getKey(), $user->accessibleClientIds(), true)) {
            throw ValidationException::withMessages(['activation' => 'This workspace is not assigned to your client portal.']);
        }
    }

    /**
     * @param  array<string, mixed>  $intake
     * @return array<string, mixed>
     */
    private function cleanIntake(string $serviceType, array $intake): array
    {
        $allowed = match ($serviceType) {
            ServiceActivation::SERVICE_DUE_DILIGENCE => [
                'target_name',
                'vendor_name',
                'industry',
                'asking_price',
                'dd_experience',
                'business_ownership_experience',
                'financial_confidence',
                'preferred_guidance',
                'timing',
                'notes',
            ],
            ServiceActivation::SERVICE_DD_PLAN_BUDGET => [
                'target_name',
                'vendor_name',
                'industry',
                'asking_price',
                'capability_mode',
                'support_level',
                'dd_experience',
                'business_ownership_experience',
                'financial_confidence',
                'preferred_guidance',
                'timing',
                'notes',
            ],
            default => ['idea_name', 'industry', 'customer', 'problem', 'timing', 'notes'],
        };

        return collect($intake)
            ->only($allowed)
            ->map(fn (mixed $value): mixed => is_string($value) ? Str::limit(trim($value), 2000, '') : $value)
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->all();
    }

    private function startRequestThread(ServiceActivation $activation, User $actor): ?Message
    {
        $activation->loadMissing('client');
        $client = $activation->client;

        if (! $client instanceof Client) {
            return null;
        }

        return $this->messages->startClientThread(
            client: $client,
            sender: $actor,
            subject: 'Service workspace request: '.$activation->clientLabel(),
            body: $this->requestThreadBody($activation),
        );
    }

    private function notifyAdvisorOfRequest(ServiceActivation $activation, ?User $advisor): void
    {
        if (! $advisor instanceof User) {
            return;
        }

        try {
            Notification::send($advisor, new ServiceActivationRequestedNotification($activation));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function ensureWorkspace(ServiceActivation $activation, User $actor): void
    {
        if ($activation->service_type === ServiceActivation::SERVICE_DUE_DILIGENCE) {
            $this->ensureDdWorkspace($activation, $actor);

            return;
        }

        if ($activation->service_type === ServiceActivation::SERVICE_DD_PLAN_BUDGET) {
            return;
        }

        $this->ensureEntrepreneurWorkspace($activation, $actor);
    }

    private function ensureDdWorkspace(ServiceActivation $activation, User $actor): void
    {
        if ($activation->related_dd_engagement_id !== null) {
            return;
        }

        $activation->loadMissing('client');
        $client = $activation->client;

        if (! $client instanceof Client) {
            return;
        }

        $advisor = $activation->advisor_id !== null
            ? User::query()->whereKey($activation->advisor_id)->first()
            : $this->leadAdvisor($client);
        $conflict = null;

        if (! $advisor instanceof User) {
            throw ValidationException::withMessages(['advisor' => 'An advisor must be assigned before a DD workspace can be activated.']);
        }

        $conflict = ConflictDeclaration::query()->create([
            'client_id' => $client->getKey(),
            'advisor_id' => $advisor->getKey(),
            'declaration' => [
                'declared' => true,
                'referral_type' => ConflictDeclarer::DUE_DILIGENCE,
                'existing_relationship' => true,
                'details' => 'Created from accepted service activation workspace fee/scope acknowledgement.',
            ],
            'declared_at' => now(),
        ]);

        $intake = (array) ($activation->intake ?? []);
        $targetName = trim((string) ($intake['target_name'] ?? 'Acquisition target to confirm'));
        $engagement = DdEngagement::query()->create([
            'client_id' => $client->getKey(),
            'target_name' => $targetName !== '' ? $targetName : 'Acquisition target to confirm',
            'target_details' => [
                'vendor_name' => $intake['vendor_name'] ?? null,
                'industry' => $intake['industry'] ?? null,
                'asking_price' => $intake['asking_price'] ?? null,
                'notes' => $intake['notes'] ?? null,
                'client_capability' => $this->ddClientCapability->fromIntake($intake, 'service_activation_intake'),
                'data_scope' => 'client_requested_acquisition_workspace',
                'service_activation_id' => $activation->getKey(),
            ],
            'status' => DdEngagement::STATUS_IN_PROGRESS,
            'recommendation' => null,
            'conflict_declaration_id' => $conflict->getKey(),
            'created_by_user_id' => $advisor?->getKey() ?? $actor->getKey(),
            'disclaimer_acknowledged_at' => now(),
        ]);

        $activation->forceFill(['related_dd_engagement_id' => $engagement->getKey()])->save();
    }

    private function ensureEntrepreneurWorkspace(ServiceActivation $activation, User $actor): void
    {
        if ($activation->related_entrepreneur_profile_id !== null) {
            return;
        }

        $activation->loadMissing('client');
        $client = $activation->client;

        if (! $client instanceof Client) {
            return;
        }

        $advisor = $activation->advisor_id !== null
            ? User::query()->whereKey($activation->advisor_id)->first()
            : $this->leadAdvisor($client);
        $intake = (array) ($activation->intake ?? []);
        $access = $this->entrepreneurAccess($activation);
        $includesPlanBudget = (bool) $access['includes_plan_budget'];
        $stage = $includesPlanBudget && ! (bool) $access['includes_idea_validation']
            ? EntrepreneurStage::BUILDING_PHASE_1->value
            : EntrepreneurStage::IDEA_VALIDATION->value;
        $profile = EntrepreneurProfile::query()->updateOrCreate(
            ['user_id' => $actor->getKey()],
            [
                'client_id' => $client->getKey(),
                'assigned_advisor_id' => $advisor?->getKey() ?? $actor->getKey(),
                'name' => (string) ($intake['idea_name'] ?? $client->trading_name ?? $client->legal_name),
                'email' => $actor->email,
                'stage' => $stage,
                'concept_summary' => $this->conceptSummary($activation),
                'gamification_on' => true,
            ],
        );

        if ($includesPlanBudget && ! (bool) $access['includes_idea_validation']) {
            $plan = $this->plans->createOrUpdateForEntrepreneur($profile, [
                'title' => 'Business plan: '.$profile->name,
                'status' => BusinessPlan::STATUS_BUILDING,
                'current_phase' => 1,
            ], $actor);

            $plan->forceFill(['client_id' => $client->getKey()])->save();
        }

        $activation->forceFill(['related_entrepreneur_profile_id' => $profile->getKey()])->save();
    }

    private function clientLabel(string $serviceType): string
    {
        return match ($serviceType) {
            ServiceActivation::SERVICE_DUE_DILIGENCE => 'Explore buying a business',
            ServiceActivation::SERVICE_DD_PLAN_BUDGET => 'DD + Business Plan & Budget',
            ServiceActivation::SERVICE_ENTREPRENEUR => 'Test new Business Idea',
            ServiceActivation::SERVICE_INTEGRATION_SCOPING => 'Systems integration scoping',
            ServiceActivation::SERVICE_INTEGRATION => 'Systems integration delivery',
            default => 'Service workspace',
        };
    }

    private function requestThreadBody(ServiceActivation $activation): string
    {
        $requestLabel = $activation->service_type === ServiceActivation::SERVICE_DD_PLAN_BUDGET
            ? 'I would like to request an FSA quote for the DD + Business Plan & Budget add-on.'
            : 'I would like to request a new workspace: '.$activation->clientLabel().'.';
        $lines = [
            $requestLabel,
        ];

        $pricingPreview = data_get($activation->metadata, 'pre_request_pricing');
        if (is_array($pricingPreview) && is_array($pricingPreview['package'] ?? null)) {
            $package = $pricingPreview['package'];
            $currency = (string) ($package['currency'] ?? 'NZD');
            $fee = isset($package['fixed_fee'])
                ? $this->formatMoney((float) $package['fixed_fee'], $currency)
                : 'pricing to confirm';

            $lines[] = sprintf(
                'Before submitting, I acknowledged the visible package/fee: %s (%s ex GST).',
                (string) ($package['client_label'] ?? $package['package_name'] ?? 'Matched package'),
                $fee,
            );

            $quoteContext = data_get($package, 'quote_context');
            if (is_array($quoteContext)) {
                $combinedFee = data_get($quoteContext, 'combined_fixed_fee');
                $ddFee = data_get($quoteContext, 'dd_package.fixed_fee');
                $addOnFee = data_get($quoteContext, 'plan_budget_fixed_fee');

                if (is_numeric($combinedFee) && is_numeric($ddFee) && is_numeric($addOnFee)) {
                    $lines[] = sprintf(
                        'Combined quote context: DD price band %s plus Business Plan & Budget %s equals %s ex GST. Add-on amount due for this request: %s ex GST.',
                        $this->formatMoney((float) $ddFee, $currency),
                        $this->formatMoney((float) $addOnFee, $currency),
                        $this->formatMoney((float) $combinedFee, $currency),
                        $this->formatMoney((float) $addOnFee, $currency),
                    );
                }
            }
        } else {
            $lines[] = 'Before submitting, I acknowledged that pricing will be confirmed before any charge or workspace access.';
        }

        $lines[] = 'Please review the request and select the active package/scope/pricing from Admin Service Rates.';

        foreach ((array) ($activation->intake ?? []) as $key => $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $lines[] = Str::headline($key).': '.trim((string) $value);
            }
        }

        return implode("\n", $lines);
    }

    private function acceptanceText(ServiceActivation $activation): string
    {
        $snapshot = (array) ($activation->selected_package_snapshot ?? []);
        $fee = isset($snapshot['fixed_fee'])
            ? number_format((float) $snapshot['fixed_fee'], 2)
            : 'the selected fee';
        $currency = (string) ($snapshot['currency'] ?? 'NZD');
        $isDdPlanBudget = $activation->service_type === ServiceActivation::SERVICE_DD_PLAN_BUDGET;
        $accessNoun = $isDdPlanBudget ? 'Business Plan & Budget module access' : 'workspace access';
        $serviceNoun = $isDdPlanBudget ? 'add-on package' : 'workspace package';
        $quoteContextText = $isDdPlanBudget
            ? $this->ddPlanBudgetAcceptanceQuoteText($snapshot, $currency)
            : '';
        $paymentText = match (true) {
            $this->activationRequiresPayment($activation) => $accessNoun.' opens only after full package payment has been received and confirmed',
            (bool) data_get($snapshot, 'pilot_fee_waiver.active', false) => 'no payment is required before '.$accessNoun.' opens because this client has an active pilot fee waiver',
            default => 'no payment is required before '.$accessNoun.' opens while fees are inactive',
        };

        return sprintf(
            'I accept the %s %s "%s" for %s %s%s. I understand the standard Terms and Conditions I already accepted for portal access continue to apply, this acknowledgement confirms the service-specific scope and fee, and %s.',
            $activation->clientLabel(),
            $serviceNoun,
            (string) ($snapshot['client_label'] ?? $snapshot['package_name'] ?? 'selected package'),
            $currency,
            $fee,
            $this->paymentSplitAcceptanceText($snapshot, $currency),
            $paymentText.$quoteContextText,
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function ddPlanBudgetAcceptanceQuoteText(array $snapshot, string $currency): string
    {
        $combinedFee = data_get($snapshot, 'quote_context.combined_fixed_fee');
        $ddFee = data_get($snapshot, 'quote_context.dd_package.fixed_fee');
        $addOnFee = data_get($snapshot, 'quote_context.plan_budget_fixed_fee');

        if (! is_numeric($combinedFee) || ! is_numeric($ddFee) || ! is_numeric($addOnFee)) {
            return '';
        }

        return sprintf(
            '. The combined DD + Business Plan & Budget quote is %s ex GST, made up of the DD price band %s plus Business Plan & Budget %s; this add-on payment is %s ex GST',
            $this->formatMoney((float) $combinedFee, $currency),
            $this->formatMoney((float) $ddFee, $currency),
            $this->formatMoney((float) $addOnFee, $currency),
            $this->formatMoney((float) $addOnFee, $currency),
        );
    }

    private function conceptSummary(ServiceActivation $activation): string
    {
        $intake = (array) ($activation->intake ?? []);

        return trim(implode("\n", array_filter([
            isset($intake['idea_name']) ? 'Idea: '.$intake['idea_name'] : null,
            isset($intake['industry']) ? 'Industry: '.$intake['industry'] : null,
            isset($intake['customer']) ? 'Customer: '.$intake['customer'] : null,
            isset($intake['problem']) ? 'Problem: '.$intake['problem'] : null,
            isset($intake['notes']) ? 'Notes: '.$intake['notes'] : null,
        ]))) ?: 'Client requested idea validation, business plan, and budget support from the advisory portal.';
    }

    /**
     * @return array<string, mixed>
     */
    private function packageSnapshotForActivation(ServiceRatePackage $package, ?Client $client = null): array
    {
        $snapshot = $package->snapshot();
        $pilot = $client instanceof Client ? $this->pilotWaivers->eligibility($client) : null;

        if (is_array($pilot) && $pilot['eligible']) {
            return $this->pilotWaivedPackageSnapshot($snapshot, $pilot);
        }

        if (! $this->serviceRates->freeAccessModeActive()) {
            return $snapshot;
        }

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
            'free_access_mode' => [
                'active' => true,
                'reason' => 'Admin service rates are inactive; package payment is not required until rates are activated.',
                'nominal_fixed_fee' => $snapshot['fixed_fee'] ?? null,
                'stripe_required' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array{eligible:bool, program_status:string, starts_at:?string, expires_at:?string}  $pilot
     * @return array<string, mixed>
     */
    private function pilotWaivedPackageSnapshot(array $snapshot, array $pilot): array
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
     * @param  array<string, mixed>  $snapshot
     */
    private function packagePaymentStatus(array $snapshot): string
    {
        if (! $this->packageRequiresPayment($snapshot)) {
            return ServiceActivation::PAYMENT_NOT_REQUIRED;
        }

        return $this->paymentSplitForSnapshot($snapshot)['requires_bank_transfer'] === true
            ? ServiceActivation::PAYMENT_DEPOSIT_PENDING
            : ServiceActivation::PAYMENT_PENDING;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function packageRequiresPayment(array $snapshot): bool
    {
        return (string) ($snapshot['billing_model'] ?? ServiceRatePackage::BILLING_FIXED_FEE) === ServiceRatePackage::BILLING_FIXED_FEE
            && (float) ($snapshot['fixed_fee'] ?? 0) > 0;
    }

    private function packageMatchesPurchasePrice(ServiceRatePackage $package, float $askingPrice): bool
    {
        $minimum = $package->purchase_price_min !== null ? (float) $package->purchase_price_min : null;
        $maximum = $package->purchase_price_max !== null ? (float) $package->purchase_price_max : null;

        if ($minimum !== null && $askingPrice < $minimum) {
            return false;
        }

        if ($maximum !== null && $askingPrice > $maximum) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $intake
     * @return array<string, mixed>|null
     */
    private function matchedDueDiligencePackageSnapshot(?Client $client, array $intake): ?array
    {
        if ($client instanceof Client) {
            $activation = ServiceActivation::query()
                ->where('client_id', $client->getKey())
                ->where('service_type', ServiceActivation::SERVICE_DUE_DILIGENCE)
                ->where('status', ServiceActivation::STATUS_ACTIVE)
                ->whereNotNull('selected_package_snapshot')
                ->latest()
                ->get()
                ->first(fn (ServiceActivation $activation): bool => is_array($activation->selected_package_snapshot));

            if ($activation instanceof ServiceActivation && is_array($activation->selected_package_snapshot)) {
                return $this->quoteLineSnapshot($activation->selected_package_snapshot);
            }
        }

        $askingPrice = $this->askingPriceFrom($client, $intake);

        if ($askingPrice === null) {
            return null;
        }

        $package = collect($this->activePackagesFor(ServiceActivation::SERVICE_DUE_DILIGENCE))
            ->first(fn (ServiceRatePackage $package): bool => $this->packageMatchesPurchasePrice($package, $askingPrice));

        if (! $package instanceof ServiceRatePackage) {
            return null;
        }

        return $this->quoteLineSnapshot($this->packageSnapshotForActivation($package, $client));
    }

    /**
     * @param  array<string, mixed>  $intake
     */
    private function askingPriceFrom(?Client $client, array $intake): ?float
    {
        $askingPrice = $intake['asking_price'] ?? null;

        if (! is_numeric($askingPrice) && $client instanceof Client) {
            $engagement = DdEngagement::query()
                ->where('client_id', $client->getKey())
                ->latest()
                ->first();
            $askingPrice = data_get($engagement?->target_details, 'asking_price');
        }

        return is_numeric($askingPrice) ? (float) $askingPrice : null;
    }

    /**
     * @param  array<string, mixed>  $addOnSnapshot
     * @param  array<string, mixed>|null  $ddSnapshot
     * @return array<string, mixed>
     */
    private function withDdPlanBudgetQuoteContext(array $addOnSnapshot, ?array $ddSnapshot): array
    {
        $addOnLine = $this->quoteLineSnapshot($addOnSnapshot);
        $addOnFee = $this->snapshotFixedFee($addOnLine);
        $ddFee = $ddSnapshot === null ? null : $this->snapshotFixedFee($ddSnapshot);
        $currency = (string) ($addOnLine['currency'] ?? $ddSnapshot['currency'] ?? 'NZD');
        $combinedFee = $addOnFee !== null && $ddFee !== null
            ? round($addOnFee + $ddFee, 2)
            : null;

        return [
            ...$addOnSnapshot,
            'quote_context' => [
                'type' => 'dd_plus_business_plan_budget',
                'summary' => $combinedFee !== null
                    ? 'DD price band plus Business Plan & Budget add-on.'
                    : 'Business Plan & Budget add-on; FSA still needs to confirm the DD price band.',
                'currency' => $currency,
                'dd_package' => $ddSnapshot,
                'plan_budget_package' => $addOnLine,
                'plan_budget_fixed_fee' => $addOnFee,
                'combined_fixed_fee' => $combinedFee,
                'amount_due_for_this_activation' => $addOnFee,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function quoteLineSnapshot(array $snapshot): array
    {
        return [
            'id' => $snapshot['id'] ?? null,
            'service_type' => $snapshot['service_type'] ?? null,
            'package_scope' => $snapshot['package_scope'] ?? null,
            'package_scope_label' => data_get($snapshot, 'access.package_scope_label')
                ?? ServiceRatePackage::packageScopeLabel(is_string($snapshot['package_scope'] ?? null) ? $snapshot['package_scope'] : null),
            'package_name' => $snapshot['package_name'] ?? null,
            'client_label' => $snapshot['client_label'] ?? null,
            'fixed_fee' => $snapshot['fixed_fee'] ?? null,
            'currency' => $snapshot['currency'] ?? 'NZD',
            'scope_description' => $snapshot['scope_description'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function snapshotFixedFee(array $snapshot): ?float
    {
        $fee = $snapshot['fixed_fee'] ?? null;

        return is_numeric($fee) ? (float) $fee : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $packages
     * @param  array<string, mixed>|null  $package
     * @return array<string, mixed>
     */
    private function pricingPreviewPayload(
        string $status,
        string $message,
        ?array $package = null,
        bool $includePackages = false,
        array $packages = [],
    ): array {
        $payload = [
            'status' => $status,
            'matched' => $package !== null,
            'message' => $message,
            'package' => $package,
            'payment_required' => $package !== null && $this->packageRequiresPayment($package),
            'free_access_mode' => (bool) data_get($package, 'free_access_mode.active', false),
            'source' => $package !== null ? 'admin_service_rate_package' : 'advisor_confirmation_required',
        ];

        if ($includePackages) {
            $payload['packages'] = $packages;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $pricingPreview
     * @return array<string, mixed>
     */
    private function storedPricingPreview(array $pricingPreview): array
    {
        return collect($pricingPreview)
            ->except('packages')
            ->all();
    }

    private function activationRequiresPayment(ServiceActivation $activation): bool
    {
        $snapshot = (array) ($activation->selected_package_snapshot ?? []);

        return (string) ($snapshot['billing_model'] ?? ServiceRatePackage::BILLING_FIXED_FEE) === ServiceRatePackage::BILLING_FIXED_FEE
            && (float) ($snapshot['fixed_fee'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{deposit_percent:float,card_deposit_amount:float|null,bank_transfer_amount:float|null,requires_bank_transfer:bool}
     */
    private function paymentSplitForSnapshot(array $snapshot): array
    {
        $paymentSplit = $snapshot['payment_split'] ?? null;

        if (is_array($paymentSplit)) {
            return [
                'deposit_percent' => (float) ($paymentSplit['deposit_percent'] ?? $snapshot['deposit_percent'] ?? 100),
                'card_deposit_amount' => isset($paymentSplit['card_deposit_amount'])
                    ? (float) $paymentSplit['card_deposit_amount']
                    : null,
                'bank_transfer_amount' => isset($paymentSplit['bank_transfer_amount'])
                    ? (float) $paymentSplit['bank_transfer_amount']
                    : null,
                'requires_bank_transfer' => (bool) ($paymentSplit['requires_bank_transfer'] ?? false),
            ];
        }

        $fixedFee = isset($snapshot['fixed_fee']) ? (float) $snapshot['fixed_fee'] : null;
        if ($fixedFee === null) {
            return [
                'deposit_percent' => 100.0,
                'card_deposit_amount' => null,
                'bank_transfer_amount' => null,
                'requires_bank_transfer' => false,
            ];
        }

        $depositPercent = min(max((float) ($snapshot['deposit_percent'] ?? 100), 0.0), 100.0);
        $cardDeposit = round($fixedFee * ($depositPercent / 100), 2);
        $bankTransfer = round(max($fixedFee - $cardDeposit, 0), 2);

        return [
            'deposit_percent' => $depositPercent,
            'card_deposit_amount' => $cardDeposit,
            'bank_transfer_amount' => $bankTransfer,
            'requires_bank_transfer' => $bankTransfer > 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function paymentSplitAcceptanceText(array $snapshot, string $currency): string
    {
        $split = $this->paymentSplitForSnapshot($snapshot);

        if (! $split['requires_bank_transfer']) {
            return '';
        }

        return sprintf(
            ', including a %s%% card deposit of %s %s and a remaining bank-transfer balance of %s %s',
            number_format($split['deposit_percent'], 2),
            $currency,
            number_format((float) $split['card_deposit_amount'], 2),
            $currency,
            number_format((float) $split['bank_transfer_amount'], 2),
        );
    }

    private function formatMoney(float $amount, string $currency): string
    {
        return $currency.' '.number_format($amount, 2);
    }

    private function testPaymentCompletionAllowed(): bool
    {
        if (! app()->environment('production')) {
            return true;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host)
            && (str_ends_with($host, '.test') || in_array($host, ['localhost', '127.0.0.1'], true));
    }

    /**
     * @return array<string, mixed>
     */
    private function entrepreneurAccess(ServiceActivation $activation): array
    {
        $snapshot = (array) ($activation->selected_package_snapshot ?? []);

        return ServiceRatePackage::accessFor(
            ServiceRatePackage::SERVICE_ENTREPRENEUR,
            (string) ($snapshot['package_scope'] ?? ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO),
        );
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function queueLearningSafely(ServiceActivation $activation, string $event, array $evidence): void
    {
        try {
            $this->context->withSystemContext(fn () => $this->queueLearning($activation, $event, $evidence));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function queueLearning(ServiceActivation $activation, string $event, array $evidence): void
    {
        $signalKey = hash('sha256', implode('|', [
            'service_activation',
            $activation->getKey(),
            $event,
            now()->toDateString(),
        ]));

        $exists = LearningUpdate::query()
            ->where('layer_id', LayerCadenceRegistry::LAYER_SERVICE_ACTIVATION)
            ->where('source->signal_key', $signalKey)
            ->exists();

        if ($exists) {
            return;
        }

        LearningUpdate::query()->create([
            'layer_id' => LayerCadenceRegistry::LAYER_SERVICE_ACTIVATION,
            'source' => [
                'type' => 'service_activation',
                'signal_key' => $signalKey,
                'service_activation_id' => $activation->getKey(),
                'event' => $event,
                'service_type' => $activation->service_type,
            ],
            'summary' => 'Service activation learning signal captured for '.$activation->clientLabel().' at '.$event.'.',
            'proposed_change' => [
                'action' => 'review_service_activation_flow',
                'automatic_application' => false,
                'requires_approval' => true,
                'candidate_surfaces' => [
                    'service_start_cards',
                    'advisor_package_selection',
                    'client_fee_acceptance',
                    'cross_service_opportunity_analytics',
                ],
            ],
            'impact_scope' => [
                'module' => 'service_activation',
                'surface' => 'client_portal_workspace_activation',
                'client_id' => $activation->client_id,
                'governance_gate' => 'advisor_or_admin_review_required',
                'direct_write_policy' => 'no_auto_pricing_scope_or_advice_changes',
                'values_guardrail' => 'honest_accurate_truthful_unbiased',
            ],
            'clients_affected' => 1,
            'magnitude' => $event === 'accepted' ? 'medium' : 'low',
            'confidence' => 0.7,
            'evidence' => [
                ...$evidence,
                'client_specific_evidence_requires_advisor_review' => true,
                'client_pii_excluded_from_summary' => true,
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }
}
