<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\BenchmarkAggregate;
use App\Models\Client;
use App\Models\Consent;
use App\Models\PeerNetworkMember;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Privacy\CohortGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class BenchmarkCommunity
{
    public const METRIC_BUSINESS_HEALTH = 'business_health_score';

    public function __construct(
        private readonly CohortGuard $cohortGuard,
        private readonly AuditWriter $audit,
    ) {}

    public function optIn(User $user, string $community, ?Client $client = null, ?User $actor = null, ?float $benchmarkScore = null): PeerNetworkMember
    {
        $community = $this->normaliseCommunity($community);

        return DB::transaction(function () use ($user, $community, $client, $actor, $benchmarkScore): PeerNetworkMember {
            /** @var Consent $consent */
            $consent = Consent::query()->create([
                'client_id' => $client?->id,
                'subject_user_id' => $user->id,
                'proposal_id' => null,
                'type' => Consent::TYPE_BENCHMARK_COMMUNITY,
                'election' => Consent::ELECTION_OPT_IN,
                'evidence' => [
                    'community' => $community,
                    'benchmark_score' => $benchmarkScore,
                    'privacy_terms' => 'aggregate_only_minimum_cohort',
                ],
                'captured_by_user_id' => $actor?->id,
                'captured_at' => now(),
            ]);

            /** @var PeerNetworkMember $member */
            $member = PeerNetworkMember::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'community' => $community,
                    'membership_type' => PeerNetworkMember::TYPE_BENCHMARK_COMMUNITY,
                ],
                [
                    'pseudonym' => $this->pseudonym($community, (string) $user->id),
                    'joined_at' => now(),
                    'consent_id' => $consent->id,
                    'status' => PeerNetworkMember::STATUS_ACTIVE,
                    'suspended_at' => null,
                    'revoked_at' => null,
                ],
            );

            $this->audit->record('benchmark_community.opted_in', subject: $member, actor: $actor, after: [
                'community' => $community,
                'consent_id' => $consent->id,
                'aggregate_only' => true,
            ]);

            return $member->refresh()->load('consent');
        });
    }

    public function revoke(PeerNetworkMember $member, ?User $actor = null): PeerNetworkMember
    {
        return DB::transaction(function () use ($member, $actor): PeerNetworkMember {
            $member->loadMissing('consent');
            $member->consent?->forceFill([
                'election' => Consent::ELECTION_OPT_OUT,
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor?->id,
            ])->save();
            $member->forceFill([
                'status' => PeerNetworkMember::STATUS_REVOKED,
                'revoked_at' => now(),
            ])->save();

            $this->audit->record('benchmark_community.revoked', subject: $member, actor: $actor, after: [
                'community' => $member->community,
                'consent_id' => $member->consent_id,
            ]);

            return $member->refresh()->load('consent');
        });
    }

    public function aggregate(string $domain, string $industryCode, ?string $quarter = null): BenchmarkAggregate
    {
        $domain = $this->normaliseCommunity($domain);
        $industryCode = $this->normaliseIndustry($industryCode);
        $quarter ??= $this->quarter();
        $scores = $this->activeScores($domain);
        $release = $this->cohortGuard->releaseAggregate(
            cohortSize: $scores->count(),
            aggregate: [
                'metric' => self::METRIC_BUSINESS_HEALTH,
                'percentile_bands' => $this->percentileBands($scores),
            ],
            suppressedMessage: 'Benchmark community aggregate suppressed below the minimum cohort.',
            metadata: [
                'domain' => $domain,
                'industry_code' => $industryCode,
                'quarter' => $quarter,
            ],
        );

        /** @var BenchmarkAggregate $aggregate */
        $aggregate = BenchmarkAggregate::query()->updateOrCreate(
            [
                'domain' => $domain,
                'industry_code' => $industryCode,
                'metric' => self::METRIC_BUSINESS_HEALTH,
                'quarter' => $quarter,
            ],
            [
                'distribution' => $release,
                'cohort_size' => $scores->count(),
                'suppressed' => (bool) ($release['suppressed'] ?? true),
                'generated_at' => now(),
            ],
        );

        $this->audit->record('benchmark_community.aggregate_generated', subject: $aggregate, after: [
            'domain' => $domain,
            'industry_code' => $industryCode,
            'cohort_size' => $aggregate->cohort_size,
            'suppressed' => $aggregate->suppressed,
            'aggregate_only' => true,
        ]);

        return $aggregate->refresh();
    }

    public function recordPrivacySignoff(BenchmarkAggregate $aggregate, User $privacyCounsel): BenchmarkAggregate
    {
        $aggregate->forceFill([
            'privacy_counsel_user_id' => $privacyCounsel->id,
            'privacy_counsel_signed_off_at' => now(),
        ])->save();

        $this->audit->record('benchmark_community.privacy_counsel_signed_off', subject: $aggregate, actor: $privacyCounsel, after: [
            'domain' => $aggregate->domain,
            'industry_code' => $aggregate->industry_code,
            'quarter' => $aggregate->quarter,
        ]);

        return $aggregate->refresh();
    }

    /**
     * @return Collection<int, float>
     */
    private function activeScores(string $community): Collection
    {
        return PeerNetworkMember::query()
            ->with('consent')
            ->where('community', $community)
            ->where('membership_type', PeerNetworkMember::TYPE_BENCHMARK_COMMUNITY)
            ->where('status', PeerNetworkMember::STATUS_ACTIVE)
            ->get()
            ->filter(fn (PeerNetworkMember $member): bool => $member->consent?->isActiveOptIn() === true)
            ->map(fn (PeerNetworkMember $member): ?float => is_numeric($member->consent?->evidence['benchmark_score'] ?? null)
                ? (float) $member->consent->evidence['benchmark_score']
                : null)
            ->filter(fn (?float $score): bool => $score !== null)
            ->values();
    }

    /**
     * @param  Collection<int, float>  $scores
     * @return array<string, int>
     */
    private function percentileBands(Collection $scores): array
    {
        if ($scores->isEmpty()) {
            return [
                'bottom_quartile' => 0,
                'lower_middle' => 0,
                'upper_middle' => 0,
                'top_quartile' => 0,
            ];
        }

        $sorted = $scores->sort()->values();
        $count = $sorted->count();

        return [
            'bottom_quartile' => (int) ceil($count * 0.25),
            'lower_middle' => (int) max(0, ceil($count * 0.5) - ceil($count * 0.25)),
            'upper_middle' => (int) max(0, ceil($count * 0.75) - ceil($count * 0.5)),
            'top_quartile' => (int) max(0, $count - ceil($count * 0.75)),
        ];
    }

    private function normaliseCommunity(string $community): string
    {
        $community = strtolower(trim($community));

        if (! in_array($community, [BenchmarkAggregate::DOMAIN_SME, BenchmarkAggregate::DOMAIN_ENTREPRENEUR], true)) {
            throw new InvalidArgumentException('Benchmark community must be sme or entrepreneur.');
        }

        return $community;
    }

    private function normaliseIndustry(string $industryCode): string
    {
        $industryCode = strtolower(trim($industryCode));

        return $industryCode === '' ? 'general' : $industryCode;
    }

    private function pseudonym(string $community, string $userId): string
    {
        return $community.'-'.Str::lower(Str::substr(hash('sha256', $community.'|'.$userId.'|benchmark'), 0, 12));
    }

    private function quarter(): string
    {
        $month = (int) now()->format('n');
        $quarter = (int) ceil($month / 3);

        return now()->format('Y').'-Q'.$quarter;
    }
}
