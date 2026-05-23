<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\LearningRollback;
use App\Models\LearningUpdate;
use App\Models\LearningUpdateImplementation;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Learning\ApprovalFlow;
use App\Services\Learning\Rollback;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class LearningRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_restores_prior_state_and_audits_the_action(): void
    {
        Carbon::setTestNow('2026-05-23 12:00:00');
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        [$update, $implementation, $terms] = $this->implementedLearningUpdate();

        $rollback = app(Rollback::class)->rollback($implementation, 'Unexpected client impact.', $admin);

        $terms->refresh();
        $implementation->refresh();
        $update->refresh();

        $this->assertInstanceOf(LearningRollback::class, $rollback);
        $this->assertSame('Prior governed wording', $terms->title);
        $this->assertFalse((bool) $terms->material);
        $this->assertTrue($implementation->rolled_back_at->equalTo(now()));
        $this->assertSame(LearningUpdate::STATUS_ROLLED_BACK, $update->status);
        $this->assertSame($rollback->id, $update->rollback_id);
        $this->assertTrue($rollback->restored_state['restored']);
        $this->assertSame(['title' => 'Prior governed wording', 'material' => false], $rollback->restored_state['attributes']);
        $this->assertDatabaseHas('audit_events', ['action' => 'learning_update.rolled_back']);
    }

    public function test_rollback_is_idempotent_for_an_implementation(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        [, $implementation] = $this->implementedLearningUpdate();
        $service = app(Rollback::class);

        $first = $service->rollback($implementation, 'First request.', $admin);
        $second = $service->rollback($implementation->refresh(), 'Second request.', $admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, LearningRollback::query()->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'learning_update.rolled_back')->count());
    }

    public function test_rolled_back_update_is_clearly_marked_in_queue_cards(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        [$update, $implementation] = $this->implementedLearningUpdate();

        app(Rollback::class)->rollback($implementation, 'Queue marking check.', $admin);

        $card = app(ApprovalFlow::class)->cards()
            ->firstWhere('id', $update->id);

        $this->assertNotNull($card);
        $this->assertSame(LearningUpdate::STATUS_ROLLED_BACK, $card['status']);
        $this->assertNotNull($card['implementations'][0]['rolled_back_at']);
    }

    public function test_admin_route_rolls_back_an_implementation(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        [, $implementation, $terms] = $this->implementedLearningUpdate();

        $this->actingAsMfa($admin)
            ->patch(route('admin.learning-update-implementations.rollback', $implementation), [
                'reason' => 'Admin requested rollback.',
            ])
            ->assertRedirect(route('admin.learning-updates.index', absolute: false));

        $this->assertSame('Prior governed wording', $terms->refresh()->title);
        $this->assertDatabaseHas('learning_rollbacks', [
            'learning_update_implementation_id' => $implementation->id,
            'reason' => 'Admin requested rollback.',
        ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }

    /**
     * @return array{0: LearningUpdate, 1: LearningUpdateImplementation, 2: TermsVersion}
     */
    private function implementedLearningUpdate(): array
    {
        $terms = TermsVersion::query()->create([
            'version' => 'learning-target',
            'title' => 'Changed governed wording',
            'material' => true,
            'notice_period_days' => 30,
        ]);

        $update = LearningUpdate::query()->create([
            'layer_id' => 5,
            'source' => ['type' => 'approval_flow_test'],
            'summary' => 'Rollback target update',
            'proposed_change' => ['action' => 'adjust_terms_copy', 'automatic_application' => false],
            'impact_scope' => ['scope' => 'terms'],
            'clients_affected' => 3,
            'magnitude' => 'low',
            'confidence' => 0.9,
            'evidence' => ['ticket' => 'WO-94'],
            'status' => LearningUpdate::STATUS_IMPLEMENTED,
            'effective_date' => now()->subDays(2),
        ]);

        $implementation = LearningUpdateImplementation::query()->create([
            'learning_update_id' => $update->id,
            'implemented_at' => now()->subDay(),
            'review_due' => now()->addDays(29),
            'target_type' => TermsVersion::class,
            'target_id' => $terms->id,
            'before_state' => [
                'title' => 'Prior governed wording',
                'material' => false,
            ],
            'after_state' => [
                'title' => 'Changed governed wording',
                'material' => true,
            ],
        ]);

        return [$update, $implementation, $terms];
    }
}
