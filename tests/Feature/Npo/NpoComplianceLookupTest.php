<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Models\Client;
use App\Models\IntegrationCall;
use App\Models\NpoComplianceAlert;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Integration\CharitiesServices\Contracts\CharitiesServicesClient;
use App\Services\Integration\CharitiesServices\FakeCharitiesServicesClient;
use App\Services\Integration\CharitiesServices\FallbackCharitiesServicesClient;
use App\Services\Integration\CharitiesServices\LiveCharitiesServicesClient;
use App\Services\Integration\CompaniesOffice\Contracts\CompaniesOfficeClient;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Resilience\RetryPolicy;
use App\Services\Npo\NpoComplianceLookup;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class NpoComplianceLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Cache::flush();
        Config::set('integrations.charities_services.live', false);
        Config::set('integrations.charities_services.api_key', null);
        Config::set('integrations.incorporated_societies.live', false);
        Config::set('integrations.incorporated_societies.api_key', null);
        Config::set('integrations.retry.attempts', 1);
        Config::set('integrations.retry.base_delay_ms', 0);
        Config::set('integrations.retry.max_delay_ms', 0);
        app(RequestContext::class)->apply('system', []);

        $this->forgetCharitiesClients();
        app()->forgetInstance(RetryPolicy::class);
        app()->forgetInstance(ResilientHttp::class);
    }

    public function test_fixture_lookups_return_charity_and_incorporated_society_status(): void
    {
        $charity = app(CharitiesServicesClient::class)->charityProfile('CC10001');
        $society = app(CompaniesOfficeClient::class)->incorporatedSocietyProfile('500001');

        $this->assertSame('Community Governance Trust', $charity['name']);
        $this->assertSame('Registered', $charity['status']);
        $this->assertSame('stub', $charity['source_badge']);
        $this->assertSame('Community Governance Society Incorporated', $society['name']);
        $this->assertTrue($society['isa_2022']['reregistered']);
        $this->assertSame('stub', $society['source_badge']);
    }

    public function test_live_charities_without_credentials_degrades_and_logs_resilience(): void
    {
        Config::set('integrations.charities_services.live', true);
        Config::set('integrations.charities_services.api_key', null);
        $this->forgetCharitiesClients();

        Http::fake(fn () => Http::response(['error' => 'registry unavailable'], 503));

        $result = app(CharitiesServicesClient::class)->charityProfile('CC10001');

        $this->assertSame('Community Governance Trust', $result['name']);
        $this->assertSame('stub_live_fallback', $result['source_badge']);
        $this->assertTrue($result['degraded']);
        $this->assertArrayHasKey('correlation_id', $result);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('integration_calls', [
            'service' => 'charities-services',
            'status' => IntegrationCall::STATUS_FAILURE,
            'attempt' => 1,
        ]);
        $this->assertDatabaseHas('integration_calls', [
            'service' => 'charities-services',
            'status' => IntegrationCall::STATUS_FALLBACK,
            'attempt' => 1,
        ]);
    }

    public function test_charity_registration_number_lookup_surfaces_compliance_status(): void
    {
        $engagement = $this->engagement(NpoLegalStructure::RegisteredCharity);

        $result = app(NpoComplianceLookup::class)->refresh(
            engagement: $engagement,
            charityRegistrationNumber: 'CC10001',
        );

        $this->assertSame('CC10001', $result['charity']['registration_number']);
        $this->assertSame('Registered', $result['charity']['status']);
        $this->assertSame('stub', $result['source_badges']['charities_services']);
        $this->assertFalse($result['analysis_blocked']);
        $this->assertSame([], $result['critical_alerts']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo.compliance_status_refreshed',
            'subject_id' => $engagement->id,
        ]);
    }

    public function test_isa_2022_alert_blocks_analysis_until_advisor_acknowledgement(): void
    {
        $engagement = $this->engagement(NpoLegalStructure::IncorporatedSociety);

        $result = app(NpoComplianceLookup::class)->refresh(
            engagement: $engagement,
            incorporatedSocietyNumber: '500099',
        );

        $alert = NpoComplianceAlert::query()->firstOrFail();

        $this->assertFalse($engagement->refresh()->isa_2022_reregistered);
        $this->assertTrue($result['analysis_blocked']);
        $this->assertTrue($alert->blocksAnalysis());
        $this->assertDatabaseHas('npo_compliance_alerts', [
            'id' => $alert->id,
            'client_id' => $engagement->client_id,
            'npo_engagement_id' => $engagement->id,
            'type' => NpoComplianceAlert::TYPE_ISA_2022_REREGISTRATION_MISSING,
            'severity' => NpoComplianceAlert::SEVERITY_CRITICAL,
            'acknowledged_at' => null,
        ]);

        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        app(NpoComplianceLookup::class)->acknowledge($alert, $advisor);

        $this->assertFalse(app(NpoComplianceLookup::class)->blocksAnalysis($engagement));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo.compliance_alert_acknowledged',
            'subject_id' => $alert->id,
            'actor_role' => User::TYPE_ADVISOR,
        ]);
    }

    private function engagement(NpoLegalStructure $structure): NpoEngagement
    {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => '9429000000000',
            'legal_name' => 'Community Governance Trust',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
        ]);

        return NpoEngagement::query()->create([
            'client_id' => $client->getKey(),
            'sub_type' => NpoEngagementSubType::GovernanceReview,
            'legal_structure' => $structure,
        ]);
    }

    private function forgetCharitiesClients(): void
    {
        app()->forgetInstance(CharitiesServicesClient::class);
        app()->forgetInstance(FakeCharitiesServicesClient::class);
        app()->forgetInstance(LiveCharitiesServicesClient::class);
        app()->forgetInstance(FallbackCharitiesServicesClient::class);
    }
}
