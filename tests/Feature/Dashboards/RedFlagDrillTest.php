<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboards;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\RedFlag;
use App\Models\User;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RedFlagDrillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_red_flag_payload_includes_sourced_trigger_and_exact_finding_drill(): void
    {
        $advisor = $this->advisor('red-flag-drill@example.test');
        $client = $this->clientFor($advisor, 'Red Flag Drill Limited');
        $sourcedFinding = $this->analysisFinding($client, now()->subHour(), [
            [
                'claim' => 'Cash balance fell 43% in 30 days.',
                'source_reference' => 'financial_snapshot:cash-balance',
            ],
        ]);
        $unsourcedFinding = $this->analysisFinding($client, now()->subHours(2), []);

        $this->redFlagFor($client, $sourcedFinding, now(), 'Cash balance warning');
        $this->redFlagFor($client, $unsourcedFinding, now()->subMinute(), 'Unsourced warning');

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('redFlags.summary.open', 2)
                ->where('redFlags.items.0.headline', 'Cash balance warning')
                ->where('redFlags.items.0.trigger.summary', 'Cash balance fell 43% in 30 days.')
                ->where('redFlags.items.0.trigger.source_reference', 'financial_snapshot:cash-balance')
                ->where(
                    'redFlags.items.0.finding_url',
                    route('advisor.clients.show', [
                        'client' => $client,
                        'focus' => 'analysis',
                        'highlight' => $sourcedFinding->id,
                    ], absolute: false),
                )
                ->where('redFlags.items.1.headline', 'Unsourced warning')
                ->where('redFlags.items.1.trigger', null));
    }

    public function test_highlighted_analysis_finding_outside_latest_twenty_is_included(): void
    {
        $advisor = $this->advisor('highlighted-red-flag@example.test');
        $client = $this->clientFor($advisor, 'Highlighted Findings Limited');
        $highlighted = $this->analysisFinding($client, now()->subDays(30), [
            [
                'claim' => 'Older critical finding remains the red-flag source.',
                'source_reference' => 'analysis:older-source',
            ],
        ], 'Older highlighted finding');

        foreach (range(1, 21) as $index) {
            $this->analysisFinding(
                $client,
                now()->subMinutes(30 - $index),
                [
                    [
                        'claim' => "Recent finding {$index}.",
                        'source_reference' => "analysis:recent-{$index}",
                    ],
                ],
                "Recent finding {$index}",
            );
        }

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client).'?focus=analysis&highlight='.$highlighted->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.analysis_findings.0.id', $highlighted->id)
                ->has('client.analysis_findings', 21)
                ->where('client.analysis_findings', function ($findings) use ($highlighted): bool {
                    return $findings->contains(fn (array $finding): bool => $finding['id'] === $highlighted->id);
                }));
    }

    private function advisor(string $email): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function clientFor(User $advisor, string $name): Client
    {
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000700',
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $client;
    }

    /**
     * @param  array<int, array{claim:string, source_reference:string}>  $attributions
     */
    private function analysisFinding(
        Client $client,
        CarbonInterface $createdAt,
        array $attributions,
        string $title = 'Critical finding',
    ): AnalysisFinding {
        $run = AnalysisRun::query()->create([
            'client_id' => $client->id,
            'module' => AnalysisModule::Financial,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [AnalysisLens::Diagnostic->value],
            'data_quality_snapshot' => [],
            'started_at' => $createdAt->copy()->subMinute(),
            'completed_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return AnalysisFinding::query()->create([
            'analysis_run_id' => $run->id,
            'client_id' => $client->id,
            'lens' => AnalysisLens::Diagnostic,
            'severity' => FindingSeverity::Critical,
            'title' => $title,
            'body' => 'Immediate advisor review is required.',
            'attributions' => $attributions,
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_NONE,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function redFlagFor(
        Client $client,
        AnalysisFinding $finding,
        CarbonInterface $surfacedAt,
        string $headline,
    ): RedFlag {
        return RedFlag::query()->create([
            'client_id' => $client->id,
            'analysis_finding_id' => $finding->id,
            'source_type' => 'analysis_finding',
            'source_key' => (string) $finding->id,
            'category' => RedFlag::CATEGORY_FINANCIAL,
            'severity' => FindingSeverity::Critical->value,
            'headline' => $headline,
            'detail' => 'Critical finding needs immediate advisor review.',
            'surfaced_at' => $surfacedAt,
        ]);
    }
}
