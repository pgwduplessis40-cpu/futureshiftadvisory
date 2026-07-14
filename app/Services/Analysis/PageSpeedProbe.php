<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Services\Integration\Resilience\ResilientHttp;

final class PageSpeedProbe
{
    public function __construct(private readonly ResilientHttp $http) {}

    /**
     * @return array<string, mixed>
     */
    public function measure(string $url): array
    {
        $key = trim((string) config('website_audit.pagespeed_api_key'));
        if ($key === '') {
            return $this->notMeasured('PageSpeed Insights API key is not configured.');
        }

        $result = $this->http->get(
            service: 'pagespeed_insights',
            endpoint: (string) config('website_audit.pagespeed_endpoint'),
            query: ['url' => $url, 'strategy' => 'mobile', 'key' => $key],
            timeoutSeconds: (int) config('website_audit.timeout_seconds', 15),
            maxAttempts: 1,
        );

        if (! $result->successful() || ! is_array($result->data)) {
            return $this->notMeasured('PageSpeed Insights did not return a measurement.');
        }

        $data = $result->data;
        $categories = (array) data_get($data, 'lighthouseResult.categories', []);
        $audits = (array) data_get($data, 'lighthouseResult.audits', []);

        return [
            'measured' => true,
            'strategy' => 'mobile',
            'performance_score' => $this->score(data_get($categories, 'performance.score')),
            'lcp_ms' => $this->numeric(data_get($audits, 'largest-contentful-paint.numericValue')),
            'cls' => $this->numeric(data_get($audits, 'cumulative-layout-shift.numericValue')),
            'inp_ms' => $this->numeric(data_get($audits, 'interaction-to-next-paint.numericValue')),
            'measured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notMeasured(string $reason): array
    {
        return [
            'measured' => false,
            'reason' => $reason,
            'performance_score' => null,
            'lcp_ms' => null,
            'cls' => null,
            'inp_ms' => null,
        ];
    }

    private function score(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) round(max(0, min(1, (float) $value)) * 100);
    }

    private function numeric(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
