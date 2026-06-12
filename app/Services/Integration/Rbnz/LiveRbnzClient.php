<?php

declare(strict_types=1);

namespace App\Services\Integration\Rbnz;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\Rbnz\Contracts\RbnzClient;
use App\Services\Integration\Resilience\IntegrationResult;
use App\Services\Integration\Resilience\ResilientHttp;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

final class LiveRbnzClient implements RbnzClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeRbnzClient $fake,
        private readonly IntegrationActivationResolver $live,
    ) {}

    public function ocr(): array
    {
        if (! $this->live->isLive('rbnz')) {
            throw IntegrationDisabledException::forService('rbnz');
        }

        if (! $this->reserveRequestCapacity()) {
            return $this->withTransportMeta(
                IntegrationResult::fallback($this->fake->fallbackOcr(), (string) Str::uuid()),
                $this->fake->fallbackOcr(),
            );
        }

        $result = $this->http->get(
            service: 'rbnz',
            endpoint: $this->endpoint('ocr'),
            cacheKey: 'integration:rbnz:ocr',
            fallback: fn (): array => $this->fake->fallbackOcr(),
            headers: $this->headers(),
        );

        $record = $result->fromFallback
            ? $this->fake->fallbackOcr()
            : $this->parseOcrPage((string) ($result->body ?? $result->data), $this->fake->fallbackOcr());

        return $this->withTransportMeta($result, $this->fake->fallbackOcr(), $record);
    }

    public function exchangeRates(): array
    {
        if (! $this->live->isLive('rbnz')) {
            throw IntegrationDisabledException::forService('rbnz');
        }

        if (! $this->reserveRequestCapacity()) {
            return $this->fake->fallbackExchangeRates();
        }

        $result = $this->http->get(
            service: 'rbnz',
            endpoint: $this->endpoint('exchange_rates'),
            cacheKey: 'integration:rbnz:exchange-rates',
            fallback: fn (): array => $this->fake->fallbackExchangeRates(),
            headers: $this->headers(),
        );

        $rates = $result->fromFallback
            ? $this->fake->fallbackExchangeRates()
            : $this->parseExchangeRatesPage((string) ($result->body ?? $result->data), $this->fake->fallbackExchangeRates());

        return array_values(array_map(
            fn (array $rate): array => $this->withTransportMeta($result, $rate, $rate),
            array_filter($rates, 'is_array'),
        ));
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function withTransportMeta(IntegrationResult $result, array $fallback, ?array $payload = null): array
    {
        $record = $payload ?? (is_array($result->data) ? $result->data : $fallback);

        return [
            ...$record,
            'source' => (string) ($record['source'] ?? 'rbnz'),
            'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
            'degraded' => $result->fromFallback || (bool) ($record['degraded'] ?? false),
            'correlation_id' => $result->correlationId,
        ];
    }

    private function endpoint(string $pathKey): string
    {
        $baseUrl = rtrim((string) Config::get('integrations.rbnz.base_url'), '/');
        $path = (string) Config::get("integrations.rbnz.paths.{$pathKey}", '/');

        return $baseUrl.'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'User-Agent' => (string) Config::get('integrations.rbnz.user_agent'),
            'Accept' => 'text/html,application/xhtml+xml',
        ];
    }

    private function reserveRequestCapacity(): bool
    {
        $limit = max(1, (int) Config::get('integrations.rbnz.max_requests_per_hour', 292));
        $key = 'integration:rbnz:approved-agent:hour:'.now()->format('YmdH');

        Cache::add($key, 0, now()->addHour()->addMinute());

        return Cache::increment($key) <= $limit;
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function parseOcrPage(string $html, array $fallback): array
    {
        $text = $this->normalisedText($html);

        if (! preg_match('/Official Cash Rate\s+([0-9]+(?:\.[0-9]+)?)\s*%/i', $text, $valueMatch)) {
            return $fallback;
        }

        $updatedAt = $this->extractDate($text, '/Updated:\s*[^,]+,\s*([0-9]{1,2}\s+[A-Za-z]+\s+[0-9]{4})/i');
        $nextUpdate = $this->extractDate($text, '/Next update:\s*[^,]+,\s*([0-9]{1,2}\s+[A-Za-z]+\s+[0-9]{4})/i');

        return [
            'indicator' => 'ocr',
            'label' => 'Official Cash Rate',
            'value' => (float) $valueMatch[1],
            'unit' => 'percent',
            'period_date' => ($updatedAt ?? now())->toDateString(),
            'source' => 'rbnz',
            'payload' => [
                'source_mode' => 'approved_website_agent',
                'page' => $this->endpoint('ocr'),
                'updated_at' => $updatedAt?->toDateString(),
                'next_update' => $nextUpdate?->toDateString(),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fallback
     * @return array<int, array<string, mixed>>
     */
    private function parseExchangeRatesPage(string $html, array $fallback): array
    {
        $text = $this->normalisedText($html);

        if (! preg_match('/Exchange Rates\s+(.+?)\s+Updated:\s*([0-9]{1,2}\s+[A-Za-z]+\s+[0-9]{4})/i', $text, $sectionMatch)) {
            return $fallback;
        }

        $rateDate = $this->dateFromText($sectionMatch[2]) ?? now();

        preg_match_all('/\b([A-Z]{3})\s+([0-9]+(?:\.[0-9]+)?)/', $sectionMatch[1], $rateMatches, PREG_SET_ORDER);

        if ($rateMatches === []) {
            return $fallback;
        }

        return array_map(fn (array $match): array => [
            'base_currency' => 'NZD',
            'quote_currency' => $match[1],
            'rate' => (float) $match[2],
            'rate_date' => $rateDate->toDateString(),
            'source' => 'rbnz',
            'payload' => [
                'source_mode' => 'approved_website_agent',
                'page' => $this->endpoint('exchange_rates'),
            ],
        ], $rateMatches);
    }

    private function normalisedText(string $html): string
    {
        $withTagSpacing = (string) preg_replace('/<[^>]+>/', ' ', $html);

        return trim((string) preg_replace(
            '/\s+/',
            ' ',
            html_entity_decode($withTagSpacing, ENT_QUOTES | ENT_HTML5),
        ));
    }

    private function extractDate(string $text, string $pattern): ?CarbonInterface
    {
        if (! preg_match($pattern, $text, $matches)) {
            return null;
        }

        return $this->dateFromText($matches[1]);
    }

    private function dateFromText(string $date): ?CarbonInterface
    {
        try {
            return Carbon::parse($date, 'Pacific/Auckland');
        } catch (\Throwable) {
            return null;
        }
    }
}
