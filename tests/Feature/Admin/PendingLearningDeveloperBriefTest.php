<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\LearningRecommendation;
use App\Models\LearningUpdate;
use App\Models\User;
use App\Services\Learning\LearningRecommendationWorkflow;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PendingLearningDeveloperBriefTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_matches_the_seven_pending_queue_ids_and_excludes_32_delivery_records(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);
        $pending = collect([
            LearningUpdate::STATUS_DETECTED,
            LearningUpdate::STATUS_DETECTED,
            LearningUpdate::STATUS_DETECTED,
            LearningUpdate::STATUS_DETECTED,
            LearningUpdate::STATUS_DETECTED,
            LearningUpdate::STATUS_STAGED,
            LearningUpdate::STATUS_DEFERRED,
        ])->map(fn (string $status, int $index): LearningUpdate => $this->learning($status, 'Pending budget observation '.$index));

        $deliveryStatuses = [
            LearningRecommendation::STATUS_APPROVED,
            LearningRecommendation::STATUS_IN_DEVELOPMENT,
            LearningRecommendation::STATUS_RELEASED,
            LearningRecommendation::STATUS_BLOCKED,
        ];
        foreach (range(0, 31) as $index) {
            $historical = $this->learning(LearningUpdate::STATUS_APPROVED, 'Historical delivery '.$index);
            $this->recommendation($historical, $deliveryStatuses[$index % 4]);
        }

        // A staged learning with a draft recommendation belongs to the separate
        // development-approval panel, not the pending-learning table or brief.
        $draft = $this->learning(LearningUpdate::STATUS_STAGED, 'Draft development recommendation');
        $this->recommendation($draft, LearningRecommendation::STATUS_DRAFT);
        foreach ([LearningUpdate::STATUS_APPROVED, LearningUpdate::STATUS_IMPLEMENTED, LearningUpdate::STATUS_REJECTED, LearningUpdate::STATUS_ROLLED_BACK] as $status) {
            $this->learning($status, 'Non-pending '.$status);
        }
        $verified = $this->learning(LearningUpdate::STATUS_APPROVED, 'Verified historical delivery');
        $this->recommendation($verified, LearningRecommendation::STATUS_VERIFIED);

        $queueIds = [];
        $this->actingAsMfa($admin)->get(route('admin.learning-updates.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($pending, &$queueIds): void {
                $page->component('admin/learning/Index')
                    ->has('pending_learning_ids', 7)
                    ->where('pending_learning_ids', function ($ids) use ($pending, &$queueIds): bool {
                        $queueIds = collect($ids)->all();
                        $this->assertEqualsCanonicalizing($pending->pluck('id')->all(), $queueIds);

                        return true;
                    })
                    ->where('cards', function ($cards) use (&$queueIds): bool {
                        $displayed = collect($cards)->whereIn('id', $queueIds)->pluck('id')->all();
                        $this->assertSame($queueIds, $displayed);

                        return true;
                    });
            });

        $response = $this->get(route('admin.learning-recommendations.developer-brief'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $brief = $response->getContent();
        $this->assertIsString($brief);
        preg_match_all('/^- Learning ID: (.+)$/m', $brief, $matches);
        $this->assertSame($queueIds, $matches[1]);
        $this->assertStringContainsString('Pending items: 7', $brief);
        $this->assertStringContainsString('"active_flag_keys"', $brief);
        $this->assertStringContainsString('"runway_mismatch"', $brief);
        $this->assertStringContainsString('Decision needed:', $brief);
        $this->assertStringContainsString('still need a human decision', $brief);
        $this->assertStringNotContainsString('Historical delivery', $brief);
        $this->assertStringNotContainsString('Verified historical delivery', $brief);
        $this->assertStringNotContainsString('Draft development recommendation', $brief);
        $this->assertStringNotContainsString('Non-pending', $brief);
        $this->assertStringNotContainsString('explicitly approved for development', $brief);
        $this->assertDatabaseCount('learning_recommendations', 34);
        foreach ($pending as $learning) {
            $this->assertSame($learning->status, $learning->fresh()->status);
        }

        $pending->first()->forceFill(['status' => LearningUpdate::STATUS_APPROVED])->save();
        $updated = $this->get(route('admin.learning-recommendations.developer-brief'))->assertOk()->getContent();
        $this->assertIsString($updated);
        $this->assertStringContainsString('Pending items: 6', $updated);
        $this->assertStringNotContainsString((string) $pending->first()->id, $updated);
    }

    public function test_empty_pending_queue_does_not_fall_back_to_delivery_history_in_download_or_command(): void
    {
        $historical = $this->learning(LearningUpdate::STATUS_APPROVED, 'Already addressed historical matter');
        $this->recommendation($historical, LearningRecommendation::STATUS_APPROVED);
        $brief = app(LearningRecommendationWorkflow::class)->developerBrief();

        $this->assertStringContainsString('Pending items: 0', $brief);
        $this->assertStringContainsString('No learnings are pending approval.', $brief);
        $this->assertStringNotContainsString('Already addressed historical matter', $brief);
        $this->artisan('learning:developer-brief')->expectsOutput($brief)->assertSuccessful();
    }

    public function test_developer_brief_preserves_authentication_and_learning_view_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $this->get(route('admin.learning-recommendations.developer-brief'))->assertRedirect(route('login'));
        $client = User::factory()->create(['user_type' => User::TYPE_CLIENT_PRIMARY]);
        $client->assignRole(User::TYPE_CLIENT_PRIMARY);
        $this->actingAsMfa($client)->get(route('admin.learning-recommendations.developer-brief'))->assertForbidden();
    }

    private function learning(string $status, string $summary): LearningUpdate
    {
        return LearningUpdate::query()->create([
            'layer_id' => 39,
            'source' => ['type' => 'entrepreneur_budget_model', 'budget_id' => 'budget-'.$summary],
            'summary' => $summary,
            'proposed_change' => ['action' => 'review_budget_guidance_calibration', 'automatic_application' => false],
            'impact_scope' => ['surface' => 'budget_model'],
            'clients_affected' => 1,
            'magnitude' => 'low',
            'confidence' => 0.68,
            'evidence' => ['active_flag_keys' => ['runway_mismatch']],
            'status' => $status,
        ]);
    }

    private function recommendation(LearningUpdate $learning, string $status): void
    {
        $learning->recommendations()->create([
            'title' => $learning->summary,
            'failure_shortfall' => 'Previously reviewed observation.',
            'impact' => 'Recorded for delivery tracking.',
            'impact_area' => 'Budget model',
            'recommendation' => 'Review budget guidance.',
            'recommendation_impact' => 'Clearer budget guidance.',
            'status' => $status,
        ]);
    }
}
