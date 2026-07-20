<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\CoachSpecialisation;
use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CoachingSignal;
use App\Models\LearningUpdate;
use App\Models\LearningUpdateImplementation;
use App\Models\Referral;
use App\Models\User;
use App\Services\Panels\Coach\SignalDetector;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CoachSignalDetectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_each_signal_maps_to_advisor_suggestion_without_auto_referral(): void
    {
        [, $client] = $this->clientWithAdvisor('signal-map-advisor@example.test', 'Signal Mapping Limited');
        $detector = app(SignalDetector::class);
        $expected = [
            CoachingSignal::TYPE_LOW_PERSONAL_COPING_STREAK => CoachSpecialisation::MENTAL_HEALTH_WELLBEING->value,
            CoachingSignal::TYPE_LEADERSHIP_CAPABILITY_GAP => CoachSpecialisation::BUSINESS_EXECUTIVE->value,
            CoachingSignal::TYPE_OWNER_READINESS_PRIMARY_CONSTRAINT => CoachSpecialisation::LIFE->value,
            CoachingSignal::TYPE_FINANCIAL_STRESS => CoachSpecialisation::FINANCIAL_WELLNESS->value,
            CoachingSignal::TYPE_CAREER_TRANSITION => CoachSpecialisation::CAREER->value,
        ];

        foreach ($expected as $signalType => $specialisation) {
            $suggestion = $detector->suggest($this->signal($client, $signalType));

            $this->assertSame($specialisation, $suggestion->suggested_specialisation);
            $this->assertFalse($suggestion->evidence['auto_referral']);
            $this->assertTrue($suggestion->evidence['advisor_final_decision_required']);
        }

        $this->assertDatabaseCount('coach_referral_suggestions', 5);
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_dashboard_surfaces_scoped_suggestions_for_advisor_review(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor('signal-dashboard@example.test', 'Signal Dashboard Limited');
        [, $otherClient] = $this->clientWithAdvisor('other-signal-dashboard@example.test', 'Other Signal Limited');
        $detector = app(SignalDetector::class);
        $detector->suggest($this->signal($client, CoachingSignal::TYPE_LEADERSHIP_CAPABILITY_GAP));
        $detector->suggest($this->signal($otherClient, CoachingSignal::TYPE_CAREER_TRANSITION));

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->loadDeferredProps('advisor-signals', fn (Assert $page): Assert => $page
                    ->where('coachSignals.summary.total', 1)
                    ->where('coachSignals.summary.auto_referrals', 0)
                    ->where('coachSignals.items.0.client_name', 'Signal Dashboard Limited')
                    ->where('coachSignals.items.0.suggested_specialisation', CoachSpecialisation::BUSINESS_EXECUTIVE->value)));
    }

    public function test_calibration_layer_emits_governed_candidates_only(): void
    {
        foreach (range(1, 3) as $index) {
            [, $client] = $this->clientWithAdvisor("calibration-{$index}@example.test", "Calibration {$index} Limited");
            $this->signal($client, CoachingSignal::TYPE_LOW_PERSONAL_COPING_STREAK);
        }

        $this
            ->artisan('panels:coach-signal-calibration', ['--minimum-signals' => 2, '--window-days' => 30])
            ->expectsOutput('Coach signal calibration completed with 1 candidate(s) created.')
            ->assertSuccessful();

        $candidate = LearningUpdate::query()
            ->where('layer_id', SignalDetector::LAYER_ID)
            ->where('source->type', 'coach_referral_signal_calibration')
            ->firstOrFail();

        $this->assertSame(LearningUpdate::STATUS_DETECTED, $candidate->status);
        $this->assertFalse($candidate->proposed_change['automatic_application']);
        $this->assertFalse($candidate->impact_scope['auto_referral']);
        $this->assertDatabaseCount('coach_referral_suggestions', 3);
        $this->assertSame(0, Referral::query()->count());
        $this->assertSame(0, LearningUpdateImplementation::query()->count());
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientWithAdvisor(string $email, string $clientName): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client];
    }

    private function signal(Client $client, string $signalType): CoachingSignal
    {
        return CoachingSignal::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => null,
            'trigger_checkin_id' => null,
            'signal_type' => $signalType,
            'severity' => 'advisor_attention',
            'status' => 'detected',
            'evidence' => [
                'threshold_table' => '15.4',
                'auto_referral' => false,
            ],
            'generated_at' => now(),
        ]);
    }
}
