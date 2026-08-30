<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\InviteToken;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * @phpstan-type PackageAccess array{includes_idea_validation:bool, includes_plan_budget:bool, package_label:string, source_activation_id:string|null}
 */
final class EntrepreneurPlanWorkspace
{
    public const ADVISORY_REQUEST_SUBJECT = 'Advisory conversion request';

    public function __construct(
        private readonly EntrepreneurInviteReconciler $entrepreneurInvites,
    ) {}

    public function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(
            $user->user_type === User::TYPE_ENTREPRENEUR
            || ($this->activeActivationForUser($user) instanceof ServiceActivation)
            || ($this->entrepreneurModuleClientForUser($user) instanceof Client),
            403,
        );

        return $user;
    }

    public function profileFor(User $user): EntrepreneurProfile
    {
        $this->entrepreneurInvites->reconcile($user);
        $activation = $this->activeActivationForUser($user);

        if ($activation instanceof ServiceActivation && $activation->related_entrepreneur_profile_id !== null) {
            return EntrepreneurProfile::query()
                ->whereKey($activation->related_entrepreneur_profile_id)
                ->firstOrFail();
        }

        $entrepreneurModuleClient = $this->entrepreneurModuleClientForUser($user);
        if ($user->user_type !== User::TYPE_ENTREPRENEUR && $entrepreneurModuleClient instanceof Client) {
            return EntrepreneurProfile::query()
                ->where('client_id', $entrepreneurModuleClient->getKey())
                ->firstOrFail();
        }

        return EntrepreneurProfile::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();
    }

    /** @return PackageAccess */
    public function packageAccess(EntrepreneurProfile $profile): array
    {
        $activation = $this->activeActivationForProfile($profile);
        $snapshot = $activation instanceof ServiceActivation
            ? (array) $activation->selected_package_snapshot
            : [];
        $profile->loadMissing('inviteToken');
        $invite = $profile->inviteToken;
        $inviteScope = $invite instanceof InviteToken
            && $invite->intended_service_type === ServiceActivation::SERVICE_ENTREPRENEUR
            ? $invite->intended_package_scope
            : null;
        $profileScope = $profile->intended_service_type === ServiceActivation::SERVICE_ENTREPRENEUR
            ? $profile->intended_package_scope
            : null;
        $scope = $activation instanceof ServiceActivation
            ? (string) ($snapshot['package_scope'] ?? ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO)
            : (string) ($profileScope ?? $inviteScope ?? ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO);
        $access = ServiceRatePackage::accessFor(
            ServiceRatePackage::SERVICE_ENTREPRENEUR,
            $scope,
        );

        return [
            ...$access,
            'package_label' => (string) ($snapshot['client_label'] ?? $snapshot['package_name'] ?? $invite?->serviceIntentLabel() ?? 'Entrepreneur workspace'),
            'source_activation_id' => $activation?->getKey(),
        ];
    }

    public function includesIdeaValidation(EntrepreneurProfile $profile): bool
    {
        return (bool) $this->packageAccess($profile)['includes_idea_validation'];
    }

    public function includesPlanBudget(EntrepreneurProfile $profile): bool
    {
        return (bool) $this->packageAccess($profile)['includes_plan_budget'];
    }

    public function packageLockedResponse(string $message): RedirectResponse
    {
        return to_route('portal.entrepreneur.plan.show')
            ->with('status', 'entrepreneur-package-locked')
            ->with('entrepreneur_plan_error', $message);
    }

    public function latestPlan(EntrepreneurProfile $profile): ?BusinessPlan
    {
        return BusinessPlan::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->with('phases.sections', 'assessments.ratingFramework.criteria')
            ->latest('updated_at')
            ->latest()
            ->first();
    }

    private function activeActivationForUser(User $user): ?ServiceActivation
    {
        $clientIds = $user->accessibleClientIds();

        if ($clientIds === []) {
            return null;
        }

        return ServiceActivation::query()
            ->whereIn('client_id', $clientIds)
            ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->whereNotNull('related_entrepreneur_profile_id')
            ->latest()
            ->first();
    }

    private function entrepreneurModuleClientForUser(User $user): ?Client
    {
        $clientIds = $user->accessibleClientIds();

        if ($clientIds === []) {
            return null;
        }

        return Client::query()
            ->whereIn('id', $clientIds)
            ->where('engagement_type', EngagementType::ENTREPRENEUR_MODULE->value)
            ->where('status', '!=', ClientStatus::SUSPENDED->value)
            ->latest()
            ->first();
    }

    private function activeActivationForProfile(EntrepreneurProfile $profile): ?ServiceActivation
    {
        $activation = ServiceActivation::query()
            ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->where('related_entrepreneur_profile_id', $profile->getKey())
            ->latest()
            ->first();

        if ($activation instanceof ServiceActivation) {
            return $activation;
        }

        if ($profile->client_id === null) {
            return null;
        }

        return ServiceActivation::query()
            ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->where('client_id', $profile->client_id)
            ->latest()
            ->first();
    }
}
