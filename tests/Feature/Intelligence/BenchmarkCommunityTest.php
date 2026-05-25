<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence;

use App\Models\BenchmarkAggregate;
use App\Models\Consent;
use App\Models\PeerNetworkMember;
use App\Models\User;
use App\Services\Intelligence\BenchmarkCommunity;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BenchmarkCommunityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('privacy.min_cohort', 5);
        $this->seed(RoleSeeder::class);
    }

    public function test_benchmark_community_consent_opt_in_revocation_and_aggregate_privacy(): void
    {
        $this->assertContains(Consent::TYPE_BENCHMARK_COMMUNITY, Consent::types());

        $service = app(BenchmarkCommunity::class);
        $members = collect([61, 72, 80, 88, 95])->map(function (int $score) use ($service): PeerNetworkMember {
            $user = User::factory()->create([
                'user_type' => User::TYPE_ADVISOR,
                'primary_role' => User::TYPE_ADVISOR,
            ]);
            $user->assignRole(User::TYPE_ADVISOR);

            return $service->optIn($user, BenchmarkAggregate::DOMAIN_SME, benchmarkScore: $score);
        });

        $aggregate = $service->aggregate(BenchmarkAggregate::DOMAIN_SME, 'retail', '2026-Q2');

        $this->assertFalse($aggregate->suppressed);
        $this->assertSame(5, $aggregate->cohort_size);
        $this->assertSame(5, array_sum($aggregate->distribution['percentile_bands']));
        $this->assertTrue($aggregate->distribution['privacy']['aggregate_only']);
        $this->assertArrayNotHasKey('values', $aggregate->distribution);
        $this->assertArrayNotHasKey('client_ids', $aggregate->distribution);
        $this->assertArrayNotHasKey('min', $aggregate->distribution);
        $this->assertArrayNotHasKey('max', $aggregate->distribution);

        $privacyCounsel = User::factory()->create([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);
        $privacyCounsel->assignRole(User::TYPE_SUPER_ADMIN);
        $signed = $service->recordPrivacySignoff($aggregate, $privacyCounsel);

        $this->assertNotNull($signed->privacy_counsel_signed_off_at);
        $this->assertSame($privacyCounsel->id, $signed->privacy_counsel_user_id);

        $revoked = $service->revoke($members->first());

        $this->assertSame(PeerNetworkMember::STATUS_REVOKED, $revoked->status);
        $this->assertSame(Consent::ELECTION_OPT_OUT, $revoked->consent->election);
        $this->assertNotNull($revoked->consent->revoked_at);

        $suppressed = $service->aggregate(BenchmarkAggregate::DOMAIN_SME, 'retail', '2026-Q2');

        $this->assertTrue($suppressed->suppressed);
        $this->assertSame(4, $suppressed->cohort_size);
        $this->assertArrayNotHasKey('percentile_bands', $suppressed->distribution);
    }

    public function test_entrepreneur_and_sme_domains_are_separate(): void
    {
        $service = app(BenchmarkCommunity::class);

        foreach (range(1, 5) as $index) {
            $service->optIn(User::factory()->create(), BenchmarkAggregate::DOMAIN_ENTREPRENEUR, benchmarkScore: 70 + $index);
            $service->optIn(User::factory()->create(), BenchmarkAggregate::DOMAIN_SME, benchmarkScore: 50 + $index);
        }

        $entrepreneur = $service->aggregate(BenchmarkAggregate::DOMAIN_ENTREPRENEUR, 'general', '2026-Q2');
        $sme = $service->aggregate(BenchmarkAggregate::DOMAIN_SME, 'general', '2026-Q2');

        $this->assertFalse($entrepreneur->suppressed);
        $this->assertFalse($sme->suppressed);
        $this->assertSame(BenchmarkAggregate::DOMAIN_ENTREPRENEUR, $entrepreneur->domain);
        $this->assertSame(BenchmarkAggregate::DOMAIN_SME, $sme->domain);
        $this->assertNotSame($entrepreneur->id, $sme->id);
    }
}
