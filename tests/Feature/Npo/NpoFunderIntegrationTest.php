<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Models\Funder;
use App\Models\IntegrationCall;
use App\Models\LearningUpdate;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\Npo\NpoFunderIntegration;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class NpoFunderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_npo_funder_sources_degrade_to_fixtures_and_emit_governed_layer_34_candidates(): void
    {
        Config::set('integrations.npo_funders.sources.community_matters_cogs.live', true);
        Config::set('integrations.npo_funders.sources.community_matters_cogs.base_url', 'https://npo-source.test');
        Config::set('integrations.npo_funders.sources.community_matters_cogs.path', 'community-matters/cogs');
        Config::set('integrations.npo_funders.sources.community_matters_cogs.api_key', 'npo-funder-test-key');
        Http::fake(['*' => Http::response(['error' => 'temporary outage'], 500)]);

        $updates = app(NpoFunderIntegration::class)->sync([NpoFunderIntegration::SOURCE_COMMUNITY_MATTERS_COGS]);

        $this->assertCount(1, $updates);
        $update = $updates->first();

        $this->assertInstanceOf(LearningUpdate::class, $update);
        $this->assertSame(LayerCadenceRegistry::LAYER_NPO_FUNDER_DATABASE_UPDATES, $update->layer_id);
        $this->assertSame(LearningUpdate::STATUS_DETECTED, $update->status);
        $this->assertSame('npo_funder_integration', $update->source['type']);
        $this->assertSame('community_matters_cogs', $update->source['provider']);
        $this->assertSame('stub_live_fallback', $update->source['source_badge']);
        $this->assertSame('update_funder_registry', $update->proposed_change['action']);
        $this->assertFalse($update->proposed_change['automatic_application']);
        $this->assertSame('COGS Community Grants', $update->proposed_change['funder']['name']);
        $this->assertTrue($update->evidence['degraded']);
        $this->assertSame(0, Funder::query()->count());

        $this->assertDatabaseHas('integration_calls', [
            'service' => 'npo-funders:community_matters_cogs',
            'status' => IntegrationCall::STATUS_FALLBACK,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo.funder_integration_candidate_detected',
            'subject_id' => $update->id,
        ]);
    }

    public function test_all_npo_funder_sources_are_available_as_flag_gated_fixtures(): void
    {
        $updates = app(NpoFunderIntegration::class)->sync();

        $this->assertCount(5, $updates);
        $this->assertSame([
            'community_matters_cogs',
            'community_matters_lottery',
            'generosity_nz',
            'fundsorter',
            'te_puni_kokiri',
        ], $updates
            ->pluck('source.provider')
            ->values()
            ->all());
        $this->assertTrue($updates->every(
            fn (LearningUpdate $update): bool => $update->layer_id === LayerCadenceRegistry::LAYER_NPO_FUNDER_DATABASE_UPDATES
                && $update->status === LearningUpdate::STATUS_DETECTED
        ));
    }
}
