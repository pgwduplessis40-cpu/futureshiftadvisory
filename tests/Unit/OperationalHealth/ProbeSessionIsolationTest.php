<?php

declare(strict_types=1);

namespace Tests\Unit\OperationalHealth;

use App\Models\User;
use App\Services\OperationalHealth\OperationalHealthCheckRunner;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ProbeSessionIsolationTest extends TestCase
{
    #[DataProvider('probeCases')]
    public function test_probe_is_isolated_and_restores_the_exact_browser_session(bool $authenticatedProbe, bool $throws): void
    {
        $admin = new User;
        $admin->forceFill(['id' => 'browser-admin', 'user_type' => User::TYPE_SUPER_ADMIN]);
        $actor = new User;
        $actor->forceFill(['id' => 'monitor-client', 'user_type' => User::TYPE_CLIENT_PRIMARY]);
        $probeUser = $authenticatedProbe ? $actor : null;
        $session = app('session')->driver();
        $session->start();
        $session->put([
            Auth::guard('web')->getName() => $admin->getAuthIdentifier(),
            'browser_only' => 'preserve this value',
        ]);
        Auth::guard('web')->setUser($admin);
        $originalData = $session->all();
        $originalId = $session->getId();
        $originalRequest = app('request');

        $kernel = Mockery::mock(Kernel::class);
        $kernel->shouldReceive('handle')->once()->andReturnUsing(function (Request $request) use ($originalId, $probeUser, $throws): Response {
            self::assertNotSame($originalId, $request->session()->getId());
            self::assertFalse($request->session()->has('browser_only'));
            self::assertFalse($request->session()->has(Auth::guard('web')->getName()));
            self::assertSame($probeUser, Auth::guard('web')->user());
            self::assertSame($probeUser, $request->user());
            self::assertNotEmpty($request->session()->token());
            $request->session()->put('probe_only', 'must not leak back');

            if ($throws) {
                throw new RuntimeException('Synthetic probe failure');
            }

            return new Response('OK');
        });
        $kernel->shouldReceive('terminate')->times($throws ? 0 : 1);
        $this->app->instance(Kernel::class, $kernel);

        $result = (new ReflectionMethod(OperationalHealthCheckRunner::class, 'dispatchInternalRequest'))
            ->invoke(app(OperationalHealthCheckRunner::class), 'GET', '/login', $probeUser);

        self::assertSame($throws ? 500 : 200, $result['status']);
        self::assertSame($originalId, $session->getId());
        self::assertSame($originalData, $session->all());
        self::assertSame($originalRequest, app('request'));
        self::assertSame($admin, Auth::guard('web')->user());
    }

    /** @return array<string, array{bool, bool}> */
    public static function probeCases(): array
    {
        return [
            'guest success' => [false, false],
            'guest exception' => [false, true],
            'monitor actor success' => [true, false],
            'monitor actor exception' => [true, true],
        ];
    }
}
