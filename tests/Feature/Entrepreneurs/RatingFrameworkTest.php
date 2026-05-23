<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Models\LearningUpdate;
use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Entrepreneurs\RatingFrameworkManager;
use App\Support\RequestContext;
use Database\Seeders\RatingFrameworkSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RatingFrameworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RatingFrameworkSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_founding_framework_seeds_eleven_placeholder_criteria_and_is_not_production_ready(): void
    {
        $framework = app(RatingFrameworkManager::class)->published();

        $this->assertSame(1, $framework->version);
        $this->assertFalse($framework->production_ready);
        $this->assertCount(11, $framework->criteria);
        $this->assertSame(array_values(RatingFramework::FOUNDING_CRITERIA), $framework->criteria->pluck('name')->all());
        $this->assertTrue($framework->criteria->every(fn ($criterion): bool => $criterion->is_placeholder));
        $this->assertFalse($framework->readinessStatus()['production_ready']);
        $this->assertStringContainsString('not production-ready', $framework->readinessStatus()['message']);
    }

    public function test_admin_edit_creates_versioned_framework_without_changing_code_or_original(): void
    {
        $admin = $this->superAdmin();
        $framework = app(RatingFrameworkManager::class)->published();

        $draft = app(RatingFrameworkManager::class)->revise($framework, [[
            'number' => 1,
            'weight' => 12.5,
            'descriptors' => [
                'exceptional' => 'Owner-set exceptional descriptor.',
                'strong' => 'Owner-set strong descriptor.',
                'developing' => 'Owner-set developing descriptor.',
                'needs_work' => 'Owner-set needs-work descriptor.',
            ],
            'is_placeholder' => false,
        ]], $admin);

        $this->assertSame(2, $draft->version);
        $this->assertSame(RatingFramework::STATUS_DRAFT, $draft->status);
        $this->assertSame($framework->id, $draft->supersedes_framework_id);
        $this->assertSame(12.5, $draft->criteria->firstWhere('number', 1)?->weight);
        $this->assertFalse((bool) $draft->criteria->firstWhere('number', 1)?->is_placeholder);
        $this->assertTrue($framework->refresh()->criteria()->firstWhere('number', 1)->is_placeholder);
    }

    public function test_grade_band_thresholds_match_phase_three_spec(): void
    {
        $manager = app(RatingFrameworkManager::class);

        $this->assertSame('exceptional', $manager->gradeBand(90));
        $this->assertSame('strong', $manager->gradeBand(75));
        $this->assertSame('developing', $manager->gradeBand(60));
        $this->assertSame('needs_work', $manager->gradeBand(59.99));
    }

    public function test_framework_evolution_goes_through_governed_learning_queue(): void
    {
        $admin = $this->superAdmin('queue-admin@example.test');
        $framework = app(RatingFrameworkManager::class)->published();

        $update = app(RatingFrameworkManager::class)->queueGovernedChange($framework, [
            'action' => 'review_rating_weight',
            'criterion_number' => 3,
            'suggested_weight' => 11.25,
        ], $admin);

        $this->assertInstanceOf(LearningUpdate::class, $update);
        $this->assertSame(LearningUpdate::STATUS_DETECTED, $update->status);
        $this->assertFalse((bool) data_get($update->proposed_change, 'automatic_application'));
        $this->assertSame('entrepreneur_rating_framework', data_get($update->source, 'type'));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.rating_framework_change_queued',
            'subject_id' => $update->id,
        ]);
    }

    private function superAdmin(string $email = 'framework-admin@example.test'): User
    {
        $admin = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        return $admin;
    }
}
