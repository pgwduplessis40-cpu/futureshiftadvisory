<?php

declare(strict_types=1);

namespace Tests\Feature\Budgets;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Services\Budgets\StrategicBudgetService;
use App\Services\Pdf\PdfRenderer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class StrategicBudgetPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_client_can_view_a_single_plan_budget_and_insights_document(): void
    {
        $renderer = new class implements PdfRenderer
        {
            public string $html = '';

            public function render(string $html): string
            {
                $this->html = $html;

                return '%PDF-1.7 strategic-budget';
            }
        };
        $this->app->instance(PdfRenderer::class, $renderer);

        $clientUser = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Document Presentation Limited',
            'trading_name' => 'Presentation Co',
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'primary_contact_user_id' => $clientUser->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $clientUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        $budget = app(StrategicBudgetService::class)->ensureForClient($client);
        $budget->forceFill([
            'business_plan_sections' => [[
                'key' => 'goals',
                'title' => 'Goals',
                'prompt' => 'What outcomes matter?',
                'answer' => 'Improve cash resilience and monthly reporting.',
            ]],
        ])->save();

        $this->actingAsMfa($clientUser)
            ->get(route('portal.business-plan-budget.document'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/StrategicPlanBudgetDocument')
                ->where('client.trading_name', 'Presentation Co')
                ->where('budget.business_plan_sections.0.answer', 'Improve cash resilience and monthly reporting.')
                ->has('budget.analytics.descriptive')
                ->where('workspaceUrl', route('portal.business-plan-budget.show', absolute: false))
                ->where('pdfUrl', route('portal.business-plan-budget.pdf', absolute: false))
                ->has('preparedAt'));

        $this->get(route('portal.business-plan-budget.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/StrategicPlanBudget')
                ->where('pdfUrl', route('portal.business-plan-budget.pdf', absolute: false)));

        $response = $this->actingAsMfa($clientUser)
            ->get(route('portal.business-plan-budget.pdf'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        self::assertStringContainsString('inline; filename=', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('Revenue, costs and net cash', $renderer->html);
        self::assertStringContainsString('Profit margin story', $renderer->html);
        self::assertStringContainsString('Cash available over time', $renderer->html);
        self::assertStringContainsString('Scenario sensitivity impact', $renderer->html);
        self::assertStringContainsString('Evidence confidence mix', $renderer->html);
    }
}
