<?php

declare(strict_types=1);

namespace App\Services\ServiceActivations;

use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ConflictDeclaration;
use App\Models\DdEngagement;
use App\Models\EntrepreneurProfile;
use App\Models\LearningUpdate;
use App\Models\Message;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Notifications\ServiceActivationRequestedNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\Messaging\MessageThreadService;
use App\Services\Plans\PlanBuilder as SharedPlanBuilder;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ServiceActivationManager
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly MessageThreadService $messages,
        private readonly SharedPlanBuilder $plans,
        private readonly RequestContext $context,
    ) {}

    /**
     * @param  array<string, mixed>  $intake
     */
    public function request(Client $client, User $actor, string $serviceType, array $intake): ServiceActivation
    {
        $serviceType = $this->normaliseServiceType($serviceType);
        $this->assertNoBlockingOpenActivation($client, $serviceType);
        $advisor = $this->leadAdvisor($client);

        $activation = DB::transaction(function () use ($client, $actor, $serviceType, $intake, $advisor): ServiceActivation {
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
            ]);

            return $activation->refresh();
        });

        if ($advisor instanceof User) {
            Notification::send($advisor, new ServiceActivationRequestedNotification($activation));
        }

        $this->queueLearning($activation, 'requested', [
            'status' => $activation->status,
            'advisor_assigned' => $advisor instanceof User,
        ]);

        return $activation;
    }

    public function selectPackage(ServiceActivation $activation, ServiceRatePackage $package, User $advisor): ServiceActivation
    {
        $activation->loadMissing('client');

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
            'selected_package_snapshot' => $package->snapshot(),
            'status' => ServiceActivation::STATUS_PACKAGE_SELECTED,
            'metadata' => [
                ...(array) ($activation->metadata ?? []),
                'package_selected_at' => now()->toIso8601String(),
                'pricing_source' => 'admin_service_rate_package',
            ],
        ])->save();

        $this->audit->record('service_activation.package_selected', subject: $activation, actor: $advisor, after: [
            'service_rate_package_id' => $package->getKey(),
            'service_type' => $activation->service_type,
            'fixed_fee' => $package->fixed_fee,
            'billing_model' => $package->billing_model,
        ]);

        $this->queueLearning($activation->refresh(), 'package_selected', [
            'package_id' => $package->getKey(),
            'billing_model' => $package->billing_model,
        ]);

        return $activation->refresh();
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

        $this->assertClientUser($client, $actor);
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

        $this->queueLearning($activation, 'accepted', [
            'package_snapshot' => $activation->selected_package_snapshot,
            'workspace_created' => true,
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

    private function normaliseServiceType(string $serviceType): string
    {
        $serviceType = trim($serviceType);

        if (! in_array($serviceType, [ServiceActivation::SERVICE_DUE_DILIGENCE, ServiceActivation::SERVICE_ENTREPRENEUR], true)) {
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
            $message = $serviceType === ServiceActivation::SERVICE_DUE_DILIGENCE
                ? 'You already have an open buying-a-business workspace. Close or cancel it before starting another DD request.'
                : 'You already have an open idea-testing workspace. Close or cancel it before starting another one.';

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
        $allowed = $serviceType === ServiceActivation::SERVICE_DUE_DILIGENCE
            ? ['target_name', 'vendor_name', 'industry', 'asking_price', 'timing', 'notes']
            : ['idea_name', 'industry', 'customer', 'problem', 'timing', 'notes'];

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

    private function ensureWorkspace(ServiceActivation $activation, User $actor): void
    {
        if ($activation->service_type === ServiceActivation::SERVICE_DUE_DILIGENCE) {
            $this->ensureDdWorkspace($activation, $actor);

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
        $profile = EntrepreneurProfile::query()->updateOrCreate(
            ['user_id' => $actor->getKey()],
            [
                'client_id' => $client->getKey(),
                'assigned_advisor_id' => $advisor?->getKey() ?? $actor->getKey(),
                'name' => (string) ($intake['idea_name'] ?? $client->trading_name ?? $client->legal_name),
                'email' => $actor->email,
                'stage' => EntrepreneurStage::BUILDING_PHASE_1->value,
                'concept_summary' => $this->conceptSummary($activation),
                'gamification_on' => true,
            ],
        );

        $plan = $this->plans->createOrUpdateForEntrepreneur($profile, [
            'title' => 'Business plan: '.$profile->name,
            'status' => BusinessPlan::STATUS_BUILDING,
            'current_phase' => 1,
        ], $actor);

        $plan->forceFill(['client_id' => $client->getKey()])->save();

        $activation->forceFill(['related_entrepreneur_profile_id' => $profile->getKey()])->save();
    }

    private function clientLabel(string $serviceType): string
    {
        return match ($serviceType) {
            ServiceActivation::SERVICE_DUE_DILIGENCE => 'Explore buying a business',
            ServiceActivation::SERVICE_ENTREPRENEUR => 'Test a new idea',
            default => 'Service workspace',
        };
    }

    private function requestThreadBody(ServiceActivation $activation): string
    {
        $lines = [
            'I would like to request a new workspace: '.$activation->clientLabel().'.',
            'Please review the request and select the active package/scope/pricing from Admin Service Rates.',
        ];

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

        return sprintf(
            'I accept the %s workspace package "%s" for %s %s. I understand the standard Terms and Conditions I already accepted for portal access continue to apply, and this acknowledgement confirms the workspace-specific scope and fee.',
            $activation->clientLabel(),
            (string) ($snapshot['client_label'] ?? $snapshot['package_name'] ?? 'selected package'),
            $currency,
            $fee,
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
