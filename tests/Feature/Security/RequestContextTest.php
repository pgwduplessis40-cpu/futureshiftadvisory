<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RequestContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_ids_are_resolved_in_system_scope_then_the_request_scope_is_restored(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Request context is enforced by PostgreSQL.');
        }

        $context = app(RequestContext::class);
        $context->apply(User::TYPE_CLIENT_PRIMARY, [], '42');

        $user = new class extends User
        {
            public ?string $observedRole = null;

            /**
             * @return array<int, string>
             */
            public function accessibleClientIds(): array
            {
                $this->observedRole = DB::selectOne(
                    "SELECT current_setting('fsa.role', true) AS value",
                )?->value;

                return ['00000000-0000-0000-0000-000000000001'];
            }
        };

        $this->assertSame(
            ['00000000-0000-0000-0000-000000000001'],
            $context->resolveClientIds($user),
        );
        $this->assertSame('system', $user->observedRole);
        $this->assertSame(
            User::TYPE_CLIENT_PRIMARY,
            DB::selectOne("SELECT current_setting('fsa.role', true) AS value")?->value,
        );
        $this->assertSame(
            '42',
            DB::selectOne("SELECT current_setting('fsa.user_id', true) AS value")?->value,
        );
    }
}
