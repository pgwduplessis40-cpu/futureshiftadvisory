<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\BrowserE2eSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

final class BrowserE2eFixtureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);

        $this->setE2eEnvironment();
        $this->seed(BrowserE2eSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }

        parent::tearDown();
    }

    public function test_browser_e2e_accounts_can_reach_their_isolated_quality_routes(): void
    {
        $advisor = User::query()->where('email', 'browser-e2e-advisor@example.test')->firstOrFail();
        $clientUser = User::query()->where('email', 'browser-e2e-client@example.test')->firstOrFail();
        $npoUser = User::query()->where('email', 'browser-e2e-npo@example.test')->firstOrFail();
        $client = Client::query()->findOrFail('00000000-0000-4000-8000-000000000001');

        self::assertTrue($advisor->hasEnabledTwoFactorAuthentication());
        self::assertTrue($clientUser->hasEnabledTwoFactorAuthentication());
        self::assertTrue($npoUser->hasEnabledTwoFactorAuthentication());
        self::assertSame(
            'JBSWY3DPEHPK3PXP',
            Fortify::currentEncrypter()->decrypt($advisor->two_factor_secret),
        );
        self::assertSame(
            ['browser-e2e-recovery-code'],
            json_decode(
                Fortify::currentEncrypter()->decrypt($advisor->two_factor_recovery_codes),
                true,
                flags: JSON_THROW_ON_ERROR,
            ),
        );

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard'));

        $this->actingAsMfa($npoUser)
            ->get(route('portal.npo-board.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/npo-board/Dashboard')
                ->where('client.legal_name', 'Browser E2E NPO Client'));

        $this->actingAsMfa($clientUser)
            ->get(route('portal.business-plan-budget.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/StrategicPlanBudget')
                ->where('client.legal_name', 'Browser E2E Isolated Client'));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.legal_name', 'Browser E2E Isolated Client'));
    }

    private function setE2eEnvironment(): void
    {
        foreach ([
            'E2E_ADVISOR_EMAIL' => 'browser-e2e-advisor@example.test',
            'E2E_ADVISOR_PASSWORD' => 'Browser-E2E-password-1',
            'E2E_ADVISOR_MFA_SECRET' => 'JBSWY3DPEHPK3PXP',
            'E2E_CLIENT_EMAIL' => 'browser-e2e-client@example.test',
            'E2E_CLIENT_PASSWORD' => 'Browser-E2E-password-2',
            'E2E_CLIENT_MFA_SECRET' => 'KRSXG5DSNFXGOIDB',
            'E2E_NPO_EMAIL' => 'browser-e2e-npo@example.test',
            'E2E_NPO_PASSWORD' => 'Browser-E2E-password-3',
            'E2E_NPO_MFA_SECRET' => 'MFRGGZDFMZTWQ2LK',
        ] as $name => $value) {
            $this->originalEnvironment[$name] = getenv($name);
            putenv("{$name}={$value}");
        }
    }
}
