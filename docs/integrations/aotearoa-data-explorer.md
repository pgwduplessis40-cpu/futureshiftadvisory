# Stats NZ Aotearoa Data Explorer

This integration uses Stats NZ Aotearoa Data Explorer (ADE) for industry and business benchmark tables and for macro indicators such as CPI, quarterly GDP, and unemployment. Manual reference-data submissions remain available as a governed fallback or correction path, but the scheduled economic refresh now calls `StatsNzClient::indicators()` directly.

## Transport

- Preferred base URL: `https://api.data.stats.govt.nz/rest`
- Alternate API Portal base URL: `http://apis.stats.govt.nz/ade-api/rest`
- Data URL shape: `/data/STATSNZ,{resourceId},{version}/{sdmx_key}`
- Response format: `format=jsondata`
- Subscription header: `Ocp-Apim-Subscription-Key`
- User-agent header: `futureshiftadvisory/1.0 (Language=php)`
- Versioning: config must specify an explicit dataflow version such as `1.0`; do not rely on `latest` for production dataflows.

Stats NZ's ADE API user guide documents the two interchangeable base URLs, the `STATSNZ,{dataflowID},{version}/{key}` data URL pattern, `format=jsondata`, and the mandatory subscription-key header.

## Configuration

`config('integrations.stats_nz.indicator_datasets')` is the source of truth for macro indicator ADE dataflows. These entries deliberately use environment variables for the ADE resource IDs and keys because the exact values should come from the Developer API query generated in Aotearoa Data Explorer:

```php
[
    [
        'key' => 'gdp_quarterly',
        'indicator' => EconomicIndicator::GDP_QUARTERLY,
        'label' => 'Gross Domestic Product quarterly change',
        'resourceId' => env('STATS_NZ_GDP_RESOURCE_ID', ''),
        'version' => env('STATS_NZ_GDP_VERSION', '1.0'),
        'sdmx_key' => env('STATS_NZ_GDP_KEY', 'all'),
        'time_dimension_key' => env('STATS_NZ_GDP_TIME_DIMENSION', 'TIME_PERIOD'),
        'dimensionAtObservation' => env('STATS_NZ_GDP_DIMENSION_AT_OBSERVATION', 'AllDimensions'),
        'unit' => 'percent',
    ],
]
```

`config('integrations.stats_nz.datasets')` is the source of truth for industry benchmark ADE dataflows:

```php
[
    [
        'key' => 'business_demography_enterprises_by_industry_size',
        'label' => 'Business demography - enterprises by industry and size',
        'resourceId' => env('STATS_NZ_BUSINESS_DEMOGRAPHY_RESOURCE_ID', 'BD_BD_001'),
        'version' => env('STATS_NZ_BUSINESS_DEMOGRAPHY_VERSION', '1.0'),
        'sdmx_key' => env('STATS_NZ_BUSINESS_DEMOGRAPHY_KEY', 'all'),
        'dimension_key' => env('STATS_NZ_BUSINESS_DEMOGRAPHY_INDUSTRY_DIMENSION', 'ANZSIC06'),
        'metric_dimension_key' => env('STATS_NZ_BUSINESS_DEMOGRAPHY_METRIC_DIMENSION', 'MEASURE'),
        'dimensionAtObservation' => env('STATS_NZ_BUSINESS_DEMOGRAPHY_DIMENSION_AT_OBSERVATION', 'AllDimensions'),
        'unit' => 'count',
    ],
]
```

The ADE client loops the configured datasets, calls `ResilientHttp::request()` with explicit headers, parses SDMX-JSON v1.0 observation data, and returns records through `StatsNzClient::indicators()` or `StatsNzClient::industryBenchmarks()`.

## Credential Handling

The `stats_nz.subscription_key` credential is resolved through the credential vault. Its registry config path remains `integrations.stats_nz.api_key` for env fallback when no DB credential row exists.

`LiveStatsNzClient` gates ADE calls with `IntegrationActivationResolver::isLive('stats_nz')`. Because `isLive` includes required-credential readiness, a missing or revoked subscription key prevents any live request and routes through the degraded fixture fallback.

## Fixtures

The macro indicator fixture lives at `database/fixtures/integration/stats-nz-economic.json`, covering CPI, quarterly GDP, and unemployment. The benchmark parser fixture lives at `database/fixtures/integration/stats-nz-industry-benchmarks.json`; it is intentionally small and shaped like SDMX-JSON v1.0 with `dataSets[].observations` plus `structure.dimensions.observation`, so parser tests cover the dimensional mapping without depending on live ADE credentials.
