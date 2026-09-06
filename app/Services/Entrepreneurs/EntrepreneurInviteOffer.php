<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\EntrepreneurProfile;
use App\Models\InviteToken;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Fees\PilotFeeWaiverManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @phpstan-import-type InviteOfferSnapshot from ServiceRatePackage */
final class EntrepreneurInviteOffer
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly PilotFeeWaiverManager $waivers,
    ) {}

    /** @return InviteOfferSnapshot */
    public function quote(string $scope, ?string $expectedVersion = null): array
    {
        $package = ServiceRatePackage::query()
            ->where('service_type', ServiceRatePackage::SERVICE_ENTREPRENEUR)
            ->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->orderByDesc('effective_from')
            ->orderBy('id')
            ->get()
            ->first(fn (ServiceRatePackage $package): bool => $package->packageScope() === $scope);

        if (! $package instanceof ServiceRatePackage
            || $package->billing_model !== ServiceRatePackage::BILLING_FIXED_FEE
            || $package->fixed_fee === null || $package->fixed_fee < 0) {
            throw ValidationException::withMessages([
                'intended_package_scope' => 'Configure an active fixed-fee package for this service before sending the invitation.',
            ]);
        }

        $snapshot = $package->snapshot();
        if ($expectedVersion !== null && ! hash_equals(self::version($snapshot), $expectedVersion)) {
            throw ValidationException::withMessages([
                'intended_package_scope' => 'This service offer has changed. Refresh the page and review the current fee before sending.',
            ]);
        }

        return $snapshot;
    }

    /** @return InviteOfferSnapshot|null */
    public function forResend(EntrepreneurProfile $profile, string $scope): ?array
    {
        /** @var InviteOfferSnapshot|null $previous */
        $previous = $profile->inviteToken?->service_offer_snapshot;
        if (is_array($previous) && ($previous['package_scope'] ?? null) === $scope) {
            return $previous;
        }

        // Existing unpriced invitations retain their original access-only terms.
        if ($previous === null) {
            return null;
        }

        return $this->quote($scope);
    }

    public function scopeFor(EntrepreneurProfile $profile): string
    {
        if (
            $profile->intended_service_type === ServiceRatePackage::SERVICE_ENTREPRENEUR
            && is_string($profile->intended_package_scope)
            && $profile->intended_package_scope !== ''
        ) {
            return ServiceRatePackage::normaliseEntrepreneurScope($profile->intended_package_scope);
        }

        $invite = $profile->inviteToken;
        if (
            $invite instanceof InviteToken
            && $invite->intended_service_type === ServiceRatePackage::SERVICE_ENTREPRENEUR
            && is_string($invite->intended_package_scope)
        ) {
            return ServiceRatePackage::normaliseEntrepreneurScope($invite->intended_package_scope);
        }

        return ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO;
    }

    public function forUser(User $user): ?InviteToken
    {
        if ($user->user_type !== User::TYPE_ENTREPRENEUR) {
            return null;
        }

        app(EntrepreneurInviteReconciler::class)->reconcile($user);

        return InviteToken::query()
            ->where('target_user_type', User::TYPE_ENTREPRENEUR)
            ->where('accepted_by_user_id', $user->getKey())
            ->whereNotNull('accepted_at')
            ->whereNotNull('service_offer_snapshot')
            ->latest('accepted_at')
            ->first();
    }

    /** @return InviteOfferSnapshot */
    public function terms(InviteToken $invite): array
    {
        /** @var InviteOfferSnapshot|null $accepted */
        $accepted = $invite->service_offer_accepted_snapshot;
        if (is_array($accepted)) {
            return $accepted;
        }

        /** @var InviteOfferSnapshot $snapshot */
        $snapshot = (array) $invite->service_offer_snapshot;
        $profile = EntrepreneurProfile::query()->where('invite_token_id', $invite->getKey())->first();
        if ($profile instanceof EntrepreneurProfile) {
            $eligibility = $this->waivers->eligibility($profile);
            if ($eligibility['eligible']) {
                $snapshot = $this->waivers->waivedPackageSnapshot($snapshot, $eligibility);
            }
        }

        return $snapshot;
    }

    public function accept(User $user, string $version): void
    {
        DB::transaction(function () use ($user, $version): void {
            $offer = $this->forUser($user);
            abort_unless($offer instanceof InviteToken, 404);
            $invite = InviteToken::query()->lockForUpdate()->findOrFail($offer->getKey());
            if ($invite->service_offer_accepted_at !== null) {
                return;
            }

            $terms = $this->terms($invite);
            if (! hash_equals(self::version($terms), $version)) {
                throw ValidationException::withMessages([
                    'accepted' => 'The offer or fee waiver has changed. Refresh this page and review the fee before accepting.',
                ]);
            }

            $invite->forceFill([
                'service_offer_accepted_snapshot' => $terms,
                'service_offer_accepted_at' => now(),
                'service_offer_accepted_by_user_id' => $user->getKey(),
            ])->save();
            $this->audit->record('entrepreneur.invite_offer_accepted', subject: $invite, actor: $user, after: [
                'service_offer' => $terms,
                'accepted_by_user_id' => $user->getKey(),
            ]);
        });
    }

    /** @param InviteOfferSnapshot $snapshot */
    public static function version(array $snapshot): string
    {
        // PostgreSQL jsonb reorders keys; compare the same terms across round trips.
        return hash('sha256', json_encode(self::normalise($snapshot), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function normalise(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::normalise($value);
            }
        }
        if (! array_is_list($values)) {
            ksort($values);
        }

        return $values;
    }
}
