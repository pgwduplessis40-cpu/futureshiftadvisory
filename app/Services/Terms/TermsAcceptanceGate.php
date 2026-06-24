<?php

declare(strict_types=1);

namespace App\Services\Terms;

use App\Models\TermsAcceptance;
use App\Models\TermsEnforcement;
use App\Models\TermsVersion;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class TermsAcceptanceGate
{
    public function latestPublishedVersion(bool $withClauses = false): ?TermsVersion
    {
        $query = TermsVersion::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');

        if ($withClauses) {
            $query->with('clauses');
        }

        return $query->first();
    }

    public function requiresAcceptance(User $user): bool
    {
        if (! $this->isEnforced()) {
            return false;
        }

        $latest = $this->latestPublishedVersion();
        if (! $latest instanceof TermsVersion) {
            return false;
        }

        $latestAcceptance = $this->activeAcceptanceForVersion($user, $latest);
        if ($latestAcceptance instanceof TermsAcceptance) {
            return false;
        }

        $activeAcceptance = $this->latestActiveAcceptance($user);
        if (! $activeAcceptance instanceof TermsAcceptance) {
            return true;
        }

        if (! $latest->material) {
            return false;
        }

        return $activeAcceptance->expires_at === null || $activeAcceptance->expires_at->isPast();
    }

    public function hasDeclinedTermsSuspension(User $user): bool
    {
        return $user->suspended_at !== null && $user->suspended_reason === 'terms_declined';
    }

    public function isEnforced(): bool
    {
        if (! $this->enforcementTableAvailable()) {
            return false;
        }

        return TermsEnforcement::query()
            ->where('scope', TermsEnforcement::SCOPE_PLATFORM)
            ->exists();
    }

    public function enforcement(): ?TermsEnforcement
    {
        if (! $this->enforcementTableAvailable()) {
            return null;
        }

        return TermsEnforcement::query()
            ->with('activatedBy')
            ->where('scope', TermsEnforcement::SCOPE_PLATFORM)
            ->first();
    }

    private function enforcementTableAvailable(): bool
    {
        try {
            return Schema::hasTable('terms_enforcements');
        } catch (Throwable) {
            return false;
        }
    }

    private function activeAcceptanceForVersion(User $user, TermsVersion $version): ?TermsAcceptance
    {
        return TermsAcceptance::query()
            ->where('user_id', $user->getKey())
            ->where('terms_version_id', $version->getKey())
            ->active()
            ->first();
    }

    private function latestActiveAcceptance(User $user): ?TermsAcceptance
    {
        return TermsAcceptance::query()
            ->where('user_id', $user->getKey())
            ->with('termsVersion')
            ->active()
            ->whereHas('termsVersion', fn ($query) => $query->published())
            ->latest('accepted_at')
            ->first();
    }
}
