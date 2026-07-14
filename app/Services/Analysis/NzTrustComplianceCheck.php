<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\Client;

final class NzTrustComplianceCheck
{
    /**
     * This is an evidence sweep, not legal advice or a legal-compliance determination.
     *
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<string, mixed>
     */
    public function assess(Client $client, array $pages): array
    {
        $haystack = strtolower(collect($pages)
            ->map(fn (array $page): string => implode(' ', [
                (string) ($page['url'] ?? ''),
                (string) ($page['title'] ?? ''),
                implode(' ', (array) data_get($page, 'headings.h1', [])),
            ]))
            ->implode(' '));
        $legalName = strtolower(trim((string) $client->legal_name));
        $tradingName = strtolower(trim((string) $client->trading_name));

        return [
            'privacy_policy_present' => str_contains($haystack, 'privacy'),
            'terms_present' => str_contains($haystack, 'terms') || str_contains($haystack, 'conditions'),
            'pricing_gst_cue' => str_contains($haystack, 'gst') || str_contains($haystack, 'incl'),
            'name_present_on_site' => ($legalName !== '' && str_contains($haystack, $legalName))
                || ($tradingName !== '' && str_contains($haystack, $tradingName)),
            'nap_vs_nzbn' => [
                'measured' => false,
                'reason' => 'NZBN address and phone comparison was not available in this audit.',
            ],
            'disclaimer' => 'Presence signals only; advisor/legal review is required for Privacy Act and Fair Trading Act compliance.',
        ];
    }
}
