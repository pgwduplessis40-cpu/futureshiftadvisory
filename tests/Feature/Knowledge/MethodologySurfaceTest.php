<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Enums\Permission;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class MethodologySurfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_internal_knowledge_user_can_browse_and_open_methodologies(): void
    {
        $advisor = $this->userOfType(User::TYPE_ADVISOR);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.knowledge.methodologies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/knowledge/Methodologies')
                ->where('filters.q', '')
                ->where('knowledgeIndexUrl', route('advisor.knowledge.index', absolute: false))
                ->has('entries')
                ->where('entries.0.show_url', fn (string $url): bool => str_starts_with($url, '/advisor/knowledge/methodologies/')));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.knowledge.methodologies.show', 'engagement.score'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/knowledge/MethodologyShow')
                ->where('entry.id', 'engagement.score')
                ->where('entry.name', 'Client Engagement Score')
                ->where('entry.parameters.0.key', 'dashboards.engagement.weights')
                ->where('indexUrl', route('advisor.knowledge.methodologies.index', absolute: false)));
    }

    public function test_methodology_routes_are_blocked_for_non_internal_users_and_guests(): void
    {
        $this->get(route('advisor.knowledge.methodologies.index'))
            ->assertRedirect(route('login'));

        foreach ([
            User::TYPE_CLIENT_PRIMARY,
            User::TYPE_CLIENT_TEAM,
            User::TYPE_ENTREPRENEUR,
            User::TYPE_BROKER,
            User::TYPE_COACH,
        ] as $type) {
            $this->actingAsMfa($this->userOfType($type))
                ->get(route('advisor.knowledge.methodologies.index'))
                ->assertForbidden();

            $this->actingAsMfa($this->userOfType($type, $type.'-detail@example.test'))
                ->get(route('advisor.knowledge.methodologies.show', 'engagement.score'))
                ->assertForbidden();
        }
    }

    public function test_methodology_routes_precede_knowledge_entry_binding_and_entry_routes_are_uuid_bound(): void
    {
        $routes = collect(Route::getRoutes())->values();
        $methodologiesIndex = $routes->search(fn ($route): bool => $route->uri() === 'advisor/knowledge/methodologies');
        $knowledgeShow = $routes->search(fn ($route): bool => $route->uri() === 'advisor/knowledge/{knowledgeEntry}');

        $this->assertIsInt($methodologiesIndex);
        $this->assertIsInt($knowledgeShow);
        $this->assertLessThan($knowledgeShow, $methodologiesIndex);

        foreach (['show', 'edit', 'update', 'destroy'] as $name) {
            $route = Route::getRoutes()->getByName("advisor.knowledge.{$name}");

            $this->assertNotNull($route);
            $this->assertArrayHasKey('knowledgeEntry', $route->wheres);
        }
    }

    public function test_only_internal_roles_have_knowledge_view_permission(): void
    {
        foreach ([User::TYPE_SUPER_ADMIN, User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR, User::TYPE_ENTREPRENEUR_MENTOR] as $role) {
            $this->assertContains(Permission::KNOWLEDGE_VIEW, Permission::roleMatrix()[$role]);
        }

        foreach ([User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM, User::TYPE_ENTREPRENEUR, User::TYPE_BROKER, User::TYPE_COACH] as $role) {
            $this->assertNotContains(Permission::KNOWLEDGE_VIEW, Permission::roleMatrix()[$role]);
        }
    }

    private function userOfType(string $type, ?string $email = null): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email ?? $type.'@example.test',
            'user_type' => $type,
            'primary_role' => $type,
        ]);
        $user->assignRole($type);

        return $user;
    }
}
