<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Enums\Permission;
use App\Http\Middleware\EnsurePermission;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

final class EnsurePermissionFallbackTest extends TestCase
{
    public function test_super_admin_user_type_grants_permission_before_seeded_role_rows_are_refreshed(): void
    {
        $request = Request::create('/admin/integration-credentials');
        $request->setUserResolver(fn (): User => new User([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]));

        $response = (new EnsurePermission)->handle(
            $request,
            fn () => response('ok'),
            Permission::CREDENTIAL_MANAGE->value,
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }
}
