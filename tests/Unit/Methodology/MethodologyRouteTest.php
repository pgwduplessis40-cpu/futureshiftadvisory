<?php

declare(strict_types=1);

namespace Tests\Unit\Methodology;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class MethodologyRouteTest extends TestCase
{
    public function test_methodology_routes_are_ordered_uuid_guarded_and_permission_gated(): void
    {
        $routes = collect(Route::getRoutes())->values();
        $methodologiesIndex = $routes->search(fn ($route): bool => $route->uri() === 'advisor/knowledge/methodologies');
        $knowledgeShow = $routes->search(fn ($route): bool => $route->uri() === 'advisor/knowledge/{knowledgeEntry}');

        $this->assertIsInt($methodologiesIndex);
        $this->assertIsInt($knowledgeShow);
        $this->assertLessThan($knowledgeShow, $methodologiesIndex);

        foreach (['advisor.knowledge.methodologies.index', 'advisor.knowledge.methodologies.show'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route);
            $this->assertContains('permission:'.Permission::KNOWLEDGE_VIEW->value, $route->gatherMiddleware());
        }

        foreach (['show', 'edit', 'update', 'destroy'] as $name) {
            $route = Route::getRoutes()->getByName("advisor.knowledge.{$name}");

            $this->assertNotNull($route);
            $this->assertArrayHasKey('knowledgeEntry', $route->wheres);
        }
    }

    public function test_only_internal_roles_carry_knowledge_view_permission(): void
    {
        foreach ([User::TYPE_SUPER_ADMIN, User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR, User::TYPE_ENTREPRENEUR_MENTOR] as $role) {
            $this->assertContains(Permission::KNOWLEDGE_VIEW, Permission::roleMatrix()[$role]);
        }

        foreach ([User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM, User::TYPE_ENTREPRENEUR, User::TYPE_BROKER, User::TYPE_COACH] as $role) {
            $this->assertNotContains(Permission::KNOWLEDGE_VIEW, Permission::roleMatrix()[$role]);
        }
    }
}
