<?php

declare(strict_types=1);

namespace Tests\Unit\Integration;

use App\Services\Integration\StatsNz\SdmxJsonIndustryBenchmarkParser;
use Tests\TestCase;

final class StatsNzSdmxJsonParserTest extends TestCase
{
    public function test_sdmx_json_v1_parser_maps_industry_benchmark_fixture(): void
    {
        $records = (new SdmxJsonIndustryBenchmarkParser)->parse($this->fixturePayload(), [
            'key' => 'business_demography_enterprises_by_industry_size',
            'label' => 'Business demography - enterprises by industry and size',
            'resourceId' => 'BD_BD_001',
            'version' => '1.0',
            'dimension_key' => 'ANZSIC06',
            'metric_dimension_key' => 'MEASURE',
            'dimensionAtObservation' => 'AllDimensions',
            'unit' => 'count',
        ]);

        $this->assertCount(4, $records);
        $this->assertSame('all', $records[0]['industry_code']);
        $this->assertSame('All industries', $records[0]['industry_label']);
        $this->assertSame('ent', $records[0]['metric']);
        $this->assertSame('Enterprises', $records[0]['metric_label']);
        $this->assertSame('2025', $records[0]['period']);
        $this->assertSame(617330.0, $records[0]['value']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixturePayload(): array
    {
        $contents = file_get_contents(base_path('database/fixtures/integration/stats-nz-industry-benchmarks.json'));
        $decoded = json_decode((string) $contents, true, flags: JSON_THROW_ON_ERROR);

        return $decoded['current']['payload'];
    }
}
