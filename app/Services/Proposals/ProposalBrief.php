<?php

declare(strict_types=1);

namespace App\Services\Proposals;

use App\Models\Proposal;
use Illuminate\Support\Str;

final class ProposalBrief
{
    public function for(Proposal $proposal): string
    {
        $scope = is_array($proposal->scope) ? $proposal->scope : [];
        $integrationPack = $scope['integration_quote_pack'] ?? null;

        if (is_array($integrationPack)) {
            return $this->integrationBrief($integrationPack);
        }

        $summary = $this->normalise($scope['summary'] ?? null);
        if ($summary !== '') {
            return Str::limit($summary, 240);
        }

        $focusAreas = $this->labels($scope['focus_areas'] ?? []);
        $services = $this->labels($proposal->services ?? []);
        $variant = $this->normalise($scope['proposal_variant'] ?? null);
        $brief = $this->defaultBrief($variant);

        if ($focusAreas !== []) {
            $brief .= ' Focus: '.implode(', ', $focusAreas).'.';
        } elseif ($services !== []) {
            $brief .= ' Services: '.implode(', ', $services).'.';
        }

        return Str::limit($brief, 240);
    }

    /**
     * @param  array<string, mixed>  $pack
     */
    private function integrationBrief(array $pack): string
    {
        $systems = collect((array) ($pack['systems'] ?? []))
            ->map(function (mixed $system): string {
                if (is_string($system)) {
                    return $this->normalise($system);
                }

                return is_array($system)
                    ? $this->normalise($system['name'] ?? $system['vendor'] ?? null)
                    : '';
            })
            ->filter()
            ->unique()
            ->take(3)
            ->implode(', ');
        $connectionCount = collect((array) ($pack['connections'] ?? []))
            ->filter(static fn (mixed $connection): bool => is_array($connection))
            ->count();
        $delivery = match ($pack['delivery_mode'] ?? null) {
            'inhouse' => 'In-house',
            'lowcode' => 'Low-code',
            'partner' => 'Delivery partner',
            'mixed' => 'Mixed delivery',
            default => 'Delivery model to be confirmed',
        };

        return trim(implode(' ', array_filter([
            $systems !== '' ? 'Systems integration: '.$systems.'.' : 'Systems integration delivery.',
            $connectionCount > 0 ? $connectionCount.' scoped '.str('connection')->plural($connectionCount).'.' : null,
            $delivery.'.',
        ])));
    }

    /**
     * @return array<int, string>
     */
    private function labels(mixed $rows): array
    {
        return collect((array) $rows)
            ->map(function (mixed $row): string {
                if (is_string($row)) {
                    return $this->normalise($row);
                }

                if (! is_array($row)) {
                    return '';
                }

                return $this->normalise(
                    $row['title']
                    ?? $row['label']
                    ?? $row['name']
                    ?? $row['focus']
                    ?? null,
                );
            })
            ->filter()
            ->unique()
            ->take(3)
            ->values()
            ->all();
    }

    private function defaultBrief(string $variant): string
    {
        return match ($variant) {
            'governance_review' => 'Governance review and board effectiveness engagement.',
            'npo_retainer' => 'Ongoing NPO advisory and impact support engagement.',
            'integration' => 'Systems integration delivery engagement.',
            default => 'Strategic advisory engagement.',
        };
    }

    private function normalise(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
