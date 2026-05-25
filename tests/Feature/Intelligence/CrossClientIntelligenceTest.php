<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\IndustryIntelligenceSignal;
use App\Models\User;
use App\Notifications\CrossClientIntelligenceNotification;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Intelligence\CrossClient;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class CrossClientIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('privacy.min_cohort', 3);
        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_cross_client_signal_is_deduped_alerted_and_anonymised(): void
    {
        Notification::fake();
        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        foreach (range(1, 3) as $index) {
            $this->finding('retail', 'Cash conversion pressure', $advisor, "94290000000{$index}");
        }

        $signals = app(CrossClient::class)->run(windowDays: 30);

        $this->assertCount(1, $signals);
        $signal = IndustryIntelligenceSignal::query()->firstOrFail();

        $this->assertFalse($signal->suppressed);
        $this->assertSame(3, $signal->cohort_size);
        $this->assertNotNull($signal->alerted_at);
        $this->assertSame('cash conversion pressure', $signal->aggregate['pattern']);
        $this->assertTrue($signal->aggregate['privacy']['aggregate_only']);
        $this->assertArrayNotHasKey('client_ids', $signal->aggregate);
        $this->assertArrayNotHasKey('finding_ids', $signal->aggregate);
        $this->assertArrayNotHasKey('values', $signal->aggregate);
        $this->assertArrayNotHasKey('min', $signal->aggregate);
        $this->assertArrayNotHasKey('max', $signal->aggregate);

        Notification::assertSentTo($advisor, CrossClientIntelligenceNotification::class);

        app(CrossClient::class)->run(windowDays: 30);

        $this->assertSame(1, IndustryIntelligenceSignal::query()->count());
    }

    public function test_cross_client_signal_is_suppressed_below_cohort(): void
    {
        Notification::fake();
        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        foreach (range(1, 2) as $index) {
            $this->finding('manufacturing', 'Supplier concentration', $advisor, "94290000001{$index}");
        }

        app(CrossClient::class)->run(windowDays: 30);

        $signal = IndustryIntelligenceSignal::query()->firstOrFail();

        $this->assertTrue($signal->suppressed);
        $this->assertSame(2, $signal->cohort_size);
        $this->assertArrayNotHasKey('pattern', $signal->aggregate);
        $this->assertNull($signal->alerted_at);

        Notification::assertNothingSent();
    }

    private function finding(string $industry, string $title, User $advisor, string $nzbn): AnalysisFinding
    {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => $nzbn,
            'legal_name' => 'Industry Signal '.$nzbn,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'registry_sources' => ['industry_code' => $industry],
            'created_by_user_id' => $advisor->id,
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);
        $run = AnalysisRun::query()->create([
            'client_id' => $client->id,
            'module' => AnalysisModule::Financial,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [AnalysisLens::Diagnostic->value],
            'data_quality_snapshot' => ['level' => Client::DATA_QUALITY_LOW],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return AnalysisFinding::query()->create([
            'analysis_run_id' => $run->id,
            'client_id' => $client->id,
            'lens' => AnalysisLens::Diagnostic,
            'severity' => FindingSeverity::High,
            'title' => $title,
            'body' => 'Synthetic cross-client pattern finding.',
            'attributions' => [
                ['claim' => $title, 'source_reference' => 'test:cross-client'],
            ],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED,
            'uncertainty' => Uncertainty::Medium,
            'bias_signals' => [],
        ]);
    }
}
