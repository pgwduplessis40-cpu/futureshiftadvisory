<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\NzResource;
use Illuminate\Database\Seeder;

final class NzResourceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->resources() as $resource) {
            NzResource::query()->updateOrCreate(
                [
                    'title' => $resource['title'],
                    'url' => $resource['url'],
                ],
                $resource,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resources(): array
    {
        return [
            [
                'industry' => 'general',
                'business_type' => 'startup',
                'title' => 'business.govt.nz Business Plan Tool',
                'url' => 'https://www.business.govt.nz/business-performance/business-planning/',
                'gap_tags' => ['foundation', 'strategy'],
                'metadata' => ['source' => 'business.govt.nz'],
                'active' => true,
            ],
            [
                'industry' => 'general',
                'business_type' => 'startup',
                'title' => 'MBIE Intellectual Property Basics',
                'url' => 'https://www.business.govt.nz/risks-and-operations/intellectual-property/',
                'gap_tags' => ['legal', 'intellectual_property'],
                'metadata' => ['source' => 'business.govt.nz'],
                'active' => true,
            ],
            [
                'industry' => 'retail',
                'business_type' => 'startup',
                'title' => 'Retail NZ Startup Guidance',
                'url' => 'https://retail.kiwi/advice/',
                'gap_tags' => ['market', 'demand'],
                'metadata' => ['source' => 'Retail NZ'],
                'active' => true,
            ],
            [
                'industry' => 'general',
                'business_type' => 'startup',
                'title' => 'Inland Revenue New Business Checklist',
                'url' => 'https://www.ird.govt.nz/roles/businesses-and-organisations/starting-a-business',
                'gap_tags' => ['financial', 'tax'],
                'metadata' => ['source' => 'Inland Revenue'],
                'active' => true,
            ],
        ];
    }
}
