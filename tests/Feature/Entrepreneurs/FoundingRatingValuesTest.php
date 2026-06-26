<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Entrepreneurs\RatingFrameworkManager;
use App\Support\RequestContext;
use Database\Seeders\FoundingRatingFrameworkValuesSeeder;
use Database\Seeders\RatingFrameworkSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FoundingRatingValuesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RatingFrameworkSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_owner_entered_values_clear_placeholders_and_mark_framework_production_ready(): void
    {
        $admin = $this->superAdmin();
        $framework = app(RatingFrameworkManager::class)->published();

        $ready = app(RatingFrameworkManager::class)->confirmFoundingValues(
            framework: $framework,
            criteriaValues: FoundingRatingFrameworkValuesSeeder::values(),
            actor: $admin,
        );

        $this->assertSame(2, $ready->version);
        $this->assertTrue($ready->production_ready);
        $this->assertTrue($ready->criteria->every(fn ($criterion): bool => ! $criterion->is_placeholder));
        $this->assertEqualsWithDelta(100.0, $ready->criteria->sum('weight'), 0.01);
        $this->assertTrue($ready->readinessStatus()['production_ready']);
        $this->assertSame('Rating framework is production-ready.', $ready->readinessStatus()['message']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.rating_framework_published',
            'subject_id' => $ready->id,
        ]);
    }

    public function test_founding_values_seeder_is_idempotent_and_publishes_ready_framework(): void
    {
        $this->seed(FoundingRatingFrameworkValuesSeeder::class);
        $this->seed(FoundingRatingFrameworkValuesSeeder::class);

        $ready = RatingFramework::query()
            ->with('criteria')
            ->where('status', RatingFramework::STATUS_PUBLISHED)
            ->latest('version')
            ->firstOrFail();

        $this->assertSame(2, $ready->version);
        $this->assertTrue($ready->production_ready);
        $this->assertCount(12, $ready->criteria);
        $this->assertSame(12.0, (float) $ready->criteria->firstWhere('number', 12)?->weight);
        $this->assertSame('Budget', $ready->criteria->firstWhere('number', 12)?->name);
        $this->assertTrue($ready->criteria->every(fn ($criterion): bool => ! $criterion->is_placeholder));
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        return $admin;
    }
}
