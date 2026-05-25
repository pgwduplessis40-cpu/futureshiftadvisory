<?php

declare(strict_types=1);

namespace Tests\Feature\Pv;

use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\IndustryWaccData;
use App\Services\Pv\DiscountRateResolver;
use App\Services\Pv\IndustryWaccRefresher;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class IndustryWaccAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_imports_wacc_data_and_resolver_uses_active_industry_rate(): void
    {
        app(RequestContext::class)->apply('system', []);

        $result = app(IndustryWaccRefresher::class)->refresh(
            fetchedAt: Carbon::parse('2026-05-25T12:00:00Z'),
            quarter: '2026Q2',
        );

        $this->assertSame(2, $result['rates_refreshed']);
        $this->assertSame(0, $result['rows_superseded']);
        $this->assertDatabaseHas('audit_events', ['action' => 'industry_wacc.refreshed']);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'nzbn' => '9429000000000',
            'legal_name' => 'WACC Client Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'registry_sources' => ['industry_code' => 'M6962'],
        ]);

        $resolved = app(DiscountRateResolver::class)->resolve($client, DiscountMethod::IndustryWacc);

        $this->assertSame(0.1125, $resolved->rate);
        $this->assertSame(DiscountMethod::IndustryWacc, $resolved->method);
        $this->assertStringStartsWith('industry_wacc_data:', $resolved->sourceAttributions[0]['source_reference']);
    }

    public function test_new_wacc_record_supersedes_prior_active_rate(): void
    {
        app(RequestContext::class)->apply('system', []);

        IndustryWaccData::query()->create([
            'industry_code' => 'M6962',
            'industry_label' => 'Management advice and related consulting services',
            'wacc_rate' => 0.1,
            'source' => 'mbie',
            'source_badge' => 'stub',
            'degraded' => false,
            'quarter' => '2026Q1',
            'fetched_at' => Carbon::parse('2026-04-01T00:00:00Z'),
            'record_hash' => hash('sha256', 'prior'),
        ]);

        app(IndustryWaccRefresher::class)->refresh(
            fetchedAt: Carbon::parse('2026-05-25T12:00:00Z'),
            quarter: '2026Q2',
        );

        $this->assertSame(1, IndustryWaccData::query()
            ->where('industry_code', 'M6962')
            ->whereNull('superseded_at')
            ->count());
        $this->assertSame(1, IndustryWaccData::query()
            ->where('industry_code', 'M6962')
            ->whereNotNull('superseded_at')
            ->count());
    }
}
