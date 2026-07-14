<?php

declare(strict_types=1);

namespace App\Services\Analysis;

final class WebsiteTechnicalProbe
{
    /**
     * @param  array<string, mixed>  $fetch
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<string, mixed>
     */
    public function assess(array $fetch, array $pages): array
    {
        $technical = (array) ($fetch['technical'] ?? []);
        $statuses = collect($pages)->pluck('http_status')->map(fn (mixed $status): int => (int) $status);
        $hosts = collect($pages)
            ->pluck('url')
            ->map(fn (mixed $url): string => strtolower((string) parse_url((string) $url, PHP_URL_HOST)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            ...$technical,
            'canonical_host' => [
                'host_count' => count($hosts),
                'hosts' => $hosts,
                'consistent' => count($hosts) <= 1,
            ],
            'http_statuses' => $statuses->values()->all(),
            'not_found_count' => $statuses->filter(fn (int $status): bool => in_array($status, [404, 410], true))->count(),
            'error_page_count' => $statuses->filter(fn (int $status): bool => $status >= 400)->count(),
        ];
    }
}
