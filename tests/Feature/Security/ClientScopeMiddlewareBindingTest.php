<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\EnforceClientScope;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Row-level security is only as good as the middleware that arms it.
 *
 * Postgres policies read `fsa.role` and `fsa.client_ids` from the session, and
 * `EnforceClientScope` is what sets them. Every other test of that middleware
 * builds it directly and calls `handle()`, which proves the class works and
 * proves nothing about whether requests go through it. Delete its lines from
 * `bootstrap/app.php` and those tests still pass while every real request
 * reaches the database with no scope applied and RLS silently stops applying.
 *
 * PLAN.md R8 names exactly that failure and asks for this assertion. The
 * registration is read back from the built kernel rather than by matching text
 * in the bootstrap file, because the question is what the application ended up
 * with, not what one file appears to say.
 */
final class ClientScopeMiddlewareBindingTest extends TestCase
{
    private function kernel(): Kernel
    {
        $kernel = app(KernelContract::class);
        self::assertInstanceOf(Kernel::class, $kernel);

        return $kernel;
    }

    /**
     * Both groups, because an unscoped API route reaches the same tables as an
     * unscoped web route and neither is a lesser exposure.
     *
     * @return list<array{0:string}>
     */
    public static function scopedGroups(): array
    {
        return [['web'], ['api']];
    }

    #[DataProvider('scopedGroups')]
    public function test_client_scope_is_bound_to_the_group(string $group): void
    {
        $groups = $this->kernel()->getMiddlewareGroups();

        self::assertArrayHasKey($group, $groups);
        self::assertContains(EnforceClientScope::class, $groups[$group],
            'Requests on the '.$group.' group would reach the database with no row-level security scope set.');
    }

    /**
     * Order is part of the binding, not a detail of it.
     *
     * `SubstituteBindings` resolves route models, and resolving one runs a
     * query. Running that query before the scope is applied would read a row
     * the request has no right to and hand it to the controller as a resolved
     * binding, so the scope has to be set first. `bootstrap/app.php` prepends
     * it to the priority list for that reason, and a reordering would restore
     * the gap without removing anything.
     */
    public function test_client_scope_runs_before_route_model_binding(): void
    {
        $priority = $this->kernel()->getMiddlewarePriority();
        $scope = array_search(EnforceClientScope::class, $priority, true);
        $bindings = array_search(SubstituteBindings::class, $priority, true);

        self::assertIsInt($scope, 'EnforceClientScope is absent from the middleware priority list.');
        self::assertIsInt($bindings, 'SubstituteBindings is absent from the middleware priority list.');
        self::assertLessThan($bindings, $scope,
            'Route model binding would query and resolve a record before the row-level security scope is applied.');
    }

    /**
     * The scope is applied by middleware and by nothing else.
     *
     * A second registration is not harmless duplication: the middleware is
     * tested as the single place the session variables are set, and a route or
     * group that applies it again, or an alias that lets a route opt out by
     * replacing it, makes the one guaranteed path conditional.
     */
    public function test_client_scope_is_not_registered_a_second_way(): void
    {
        $kernel = $this->kernel();

        self::assertNotContains(EnforceClientScope::class, $kernel->getGlobalMiddleware(),
            'Global registration would run the scope twice on every grouped request.');
        self::assertNotContains(EnforceClientScope::class, $kernel->getRouteMiddleware(),
            'An alias would let a route opt in or out of row-level security.');

        foreach ($kernel->getMiddlewareGroups() as $group => $middleware) {
            self::assertLessThanOrEqual(1, count(array_keys($middleware, EnforceClientScope::class, true)),
                'The '.$group.' group applies the scope more than once.');
        }
    }
}
