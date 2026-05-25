<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule as AnalysisModuleEnum;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\LearningUpdate;
use App\Models\User;
use App\Notifications\BiasMonitorSignalNotification;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Integrity\BiasCalibration;
use App\Services\Ai\Integrity\BiasMonitor;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class BiasCalibrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_bias_calibration_creates_alerted_governed_candidate_for_systematic_skew(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->create([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);
        $superAdmin->assignRole(User::TYPE_SUPER_ADMIN);

        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        foreach (range(1, 3) as $index) {
            $this->findingForBiasCalibration(
                module: AnalysisModuleEnum::Financial,
                entityType: 'Retail',
                severity: FindingSeverity::High,
                advisor: $index === 1 ? $advisor : null,
            );
        }

        foreach (range(1, 3) as $index) {
            $this->findingForBiasCalibration(
                module: AnalysisModuleEnum::Financial,
                entityType: 'Consulting',
                severity: FindingSeverity::Info,
            );
        }

        $this->artisan('analysis:bias-calibration', [
            '--min-findings' => 3,
            '--skew-threshold' => 0.5,
            '--window-days' => 30,
        ])->assertExitCode(0);

        $monitorCandidate = LearningUpdate::query()
            ->where('layer_id', BiasMonitor::LAYER_ID)
            ->where('source->type', 'bias_monitor')
            ->firstOrFail();
        $calibrationCandidate = LearningUpdate::query()
            ->where('layer_id', BiasCalibration::LAYER_ID)
            ->where('source->type', 'bias_calibration')
            ->firstOrFail();

        $this->assertSame($monitorCandidate->source['signal_key'], $calibrationCandidate->source['signal_key']);
        $this->assertSame($monitorCandidate->id, $calibrationCandidate->source['bias_monitor_update_id']);
        $this->assertSame('calibrate_bias_monitoring_or_analysis_prompt', $calibrationCandidate->proposed_change['action']);
        $this->assertFalse($calibrationCandidate->proposed_change['automatic_application']);
        $this->assertTrue($calibrationCandidate->proposed_change['requires_approval']);
        $this->assertSame(3, $calibrationCandidate->clients_affected);
        $this->assertSame('candidate_only_no_automatic_correction', $calibrationCandidate->evidence['guardrail']);
        $this->assertDatabaseCount('learning_update_implementations', 0);
        $this->assertDatabaseHas('learning_layer_runs', [
            'layer_id' => BiasCalibration::LAYER_ID,
            'status' => 'completed',
            'candidates_created' => 1,
        ]);

        Notification::assertSentTo(
            $superAdmin,
            BiasMonitorSignalNotification::class,
            fn (BiasMonitorSignalNotification $notification): bool => $notification->candidate->is($calibrationCandidate),
        );
        Notification::assertSentTo(
            $advisor,
            BiasMonitorSignalNotification::class,
            fn (BiasMonitorSignalNotification $notification): bool => $notification->candidate->is($calibrationCandidate),
        );

        $this->artisan('analysis:bias-calibration', [
            '--min-findings' => 3,
            '--skew-threshold' => 0.5,
            '--window-days' => 30,
        ])->assertExitCode(0);

        $this->assertSame(1, LearningUpdate::query()
            ->where('source->type', 'bias_calibration')
            ->count());
    }

    private function findingForBiasCalibration(
        AnalysisModuleEnum $module,
        string $entityType,
        FindingSeverity $severity,
        ?User $advisor = null,
    ): AnalysisFinding {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Bias Calibration '.fake()->unique()->company(),
            'entity_type' => $entityType,
            'gst_registered' => true,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor?->getKey(),
        ]);

        if ($advisor instanceof User) {
            ClientTeamMember::query()->create([
                'client_id' => $client->id,
                'user_id' => $advisor->getKey(),
                'role' => 'lead_advisor',
                'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
            ]);
        }

        $run = AnalysisRun::query()->create([
            'client_id' => $client->id,
            'module' => $module,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [AnalysisLens::Diagnostic->value],
            'data_quality_snapshot' => ['level' => Client::DATA_QUALITY_LOW],
            'ai_model' => 'bias-calibration-test',
            'prompt_version' => 'wo103-bias-calibration-test',
            'prompt_hash' => hash('sha256', $client->id.$module->value),
            'started_at' => now(),
            'completed_at' => now(),
            'created_by_user_id' => $advisor?->getKey(),
        ]);

        return AnalysisFinding::query()->create([
            'analysis_run_id' => $run->id,
            'client_id' => $client->id,
            'lens' => AnalysisLens::Diagnostic,
            'severity' => $severity,
            'title' => "{$entityType} severity sample",
            'body' => 'Synthetic finding for governed bias-calibration monitoring.',
            'attributions' => [
                ['claim' => 'Synthetic skew sample', 'source_reference' => 'questionnaire:wo103-bias-calibration'],
            ],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_NONE,
            'uncertainty' => Uncertainty::Medium,
            'bias_signals' => [],
        ]);
    }
}
