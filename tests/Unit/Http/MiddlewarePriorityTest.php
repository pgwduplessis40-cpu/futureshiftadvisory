<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Middleware\EnforceClientScope;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Tests\TestCase;

final class MiddlewarePriorityTest extends TestCase
{
    public function test_rls_scope_is_applied_after_authentication_and_before_route_model_binding(): void
    {
        $priority = app(HttpKernel::class)->getMiddlewarePriority();

        $authIndex = array_search(AuthenticatesRequests::class, $priority, true);
        $scopeIndex = array_search(EnforceClientScope::class, $priority, true);
        $bindingIndex = array_search(SubstituteBindings::class, $priority, true);

        $this->assertNotFalse($authIndex);
        $this->assertNotFalse($scopeIndex);
        $this->assertNotFalse($bindingIndex);
        $this->assertGreaterThan($authIndex, $scopeIndex);
        $this->assertLessThan($bindingIndex, $scopeIndex);
    }
}
