<?php

declare(strict_types=1);

namespace App\Services\Terms;

use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;

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
