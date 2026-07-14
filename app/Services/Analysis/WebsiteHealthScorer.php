<?php

declare(strict_types=1);

namespace App\Services\Analysis;

final class WebsiteHealthScorer
{
    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $technical
     * @param  array<string, mixed>  $performance
     * @param  array<string, mixed>  $compliance
     * @return array<string, mixed>
     */
    public function score(array $pages, array $technical, array $performance, array $compliance): array
    {
        $successfulPages = collect($pages)->filter(fn (array $page): bool => (int) ($page['http_status'] ?? 0) >= 200 && (int) ($page['http_status'] ?? 0) < 300);
        if ($successfulPages->isEmpty()) {
            return [
                'method_version' => 'website_health_v1',
                'findability' => null,
                'credibility' => null,
                'conversion' => null,
                'technical' => null,
                'overall' => null,
                'reason' => 'No successful HTML page was available for scoring.',
            ];
        }

        $homepage = $successfulPages->first();
        $findability = 30
            + ($homepage['title'] ? 20 : 0)
            + ($homepage['meta_description'] ? 15 : 0)
            + ($homepage['canonical'] ? 10 : 0)
            + (collect($pages)->contains(fn (array $page): bool => (array) ($page['schema_types'] ?? []) !== []) ? 15 : 0)
            + (data_get($technical, 'sitemap.status') === 200 ? 10 : 0);
        $credibility = 30
            + (($compliance['privacy_policy_present'] ?? false) ? 25 : 0)
            + (($compliance['terms_present'] ?? false) ? 15 : 0)
            + (($compliance['name_present_on_site'] ?? false) ? 15 : 0)
            + (($technical['https'] ?? false) ? 15 : 0);
        $conversion = 25
            + ($successfulPages->contains(fn (array $page): bool => (array) ($page['cta_text'] ?? []) !== []) ? 35 : 0)
            + ($successfulPages->contains(fn (array $page): bool => (bool) ($page['has_form'] ?? false)) ? 20 : 0)
            + ($successfulPages->contains(fn (array $page): bool => (bool) ($page['has_phone_link'] ?? false) || (bool) ($page['has_email_link'] ?? false)) ? 20 : 0);
        $technicalScore = 45
            + (($technical['https'] ?? false) ? 15 : 0)
            + ($successfulPages->contains(fn (array $page): bool => ! empty($page['viewport'])) ? 15 : 0)
            + ((int) ($technical['error_page_count'] ?? 0) === 0 ? 15 : 0)
            + ((bool) data_get($technical, 'canonical_host.consistent', false) ? 10 : 0);

        if (($performance['measured'] ?? false) === true && is_numeric($performance['performance_score'] ?? null)) {
            $technicalScore = (int) round(($technicalScore * 0.7) + ((int) $performance['performance_score'] * 0.3));
        }

        $scores = [
            'findability' => $this->normalise($findability),
            'credibility' => $this->normalise($credibility),
            'conversion' => $this->normalise($conversion),
            'technical' => $this->normalise($technicalScore),
        ];

        return [
            'method_version' => 'website_health_v1',
            ...$scores,
            'overall' => (int) round(array_sum($scores) / count($scores)),
        ];
    }

    private function normalise(int|float $score): int
    {
        return (int) max(0, min(100, round($score)));
    }
}
