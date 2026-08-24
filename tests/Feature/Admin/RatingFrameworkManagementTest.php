<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\RatingCriterion;
use App\Models\RatingFramework;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RatingFrameworkSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RatingFrameworkManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RatingFrameworkSeeder::class]);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_super_admin_versions_and_publishes_a_complete_rating_framework(): void
    {
        $admin = $this->superAdmin();
        $published = RatingFramework::query()->with('criteria')->firstOrFail();

        $this->actingAsMfa($admin)
            ->get(route('admin.rating-frameworks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/rating-frameworks/Index')
                ->has('frameworks', 1)
                ->where('frameworks.0.id', $published->getKey())
                ->where('frameworks.0.status', RatingFramework::STATUS_PUBLISHED)
                ->where('frameworks.0.publish_url', null)
                ->has('frameworks.0.criteria', 12)
                ->where('draft_url', route('admin.rating-frameworks.drafts.store', absolute: false))
            );

        $criteria = $published->criteria
            ->map(fn (RatingCriterion $criterion): array => [
                'number' => $criterion->number,
                'name' => $criterion->name,
                'weight' => $criterion->weight,
                'descriptors' => [
                    'exceptional' => "Exceptional evidence for {$criterion->name}.",
                    'strong' => "Strong evidence for {$criterion->name}.",
                    'developing' => "Developing evidence for {$criterion->name}.",
                    'needs_work' => "Needs-work evidence for {$criterion->name}.",
                ],
            ])
            ->all();

        $this->actingAsMfa($admin)
            ->post(route('admin.rating-frameworks.drafts.store'), ['criteria' => $criteria])
            ->assertRedirect(route('admin.rating-frameworks.index', absolute: false))
            ->assertSessionHas('status', 'rating-framework-draft-created');

        $draft = RatingFramework::query()
            ->with('criteria')
            ->where('status', RatingFramework::STATUS_DRAFT)
            ->firstOrFail();

        $this->assertSame(2, $draft->version);
        $this->assertTrue($draft->production_ready);
        $this->assertCount(12, $draft->criteria);
        $this->assertTrue($draft->criteria->every(fn (RatingCriterion $criterion): bool => ! $criterion->is_placeholder));

        $this->actingAsMfa($admin)
            ->post(route('admin.rating-frameworks.publish', $draft))
            ->assertRedirect(route('admin.rating-frameworks.index', absolute: false))
            ->assertSessionHas('status', 'rating-framework-published');

        $this->assertSame(RatingFramework::STATUS_PUBLISHED, $draft->refresh()->status);
        $this->assertNotNull($draft->published_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.rating_framework_revised',
            'subject_id' => $draft->getKey(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.rating_framework_published',
            'subject_id' => $draft->getKey(),
        ]);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        return $admin;
    }
}
