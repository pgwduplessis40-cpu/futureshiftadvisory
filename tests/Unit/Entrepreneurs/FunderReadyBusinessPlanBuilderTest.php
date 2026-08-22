<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Services\Entrepreneurs\FunderReadyBusinessPlanBuilder;
use App\Services\Entrepreneurs\PlanRequirements;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

final class FunderReadyBusinessPlanBuilderTest extends TestCase
{
    public function test_master_template_mapping_covers_every_canonical_plan_requirement(): void
    {
        $mapping = (new ReflectionClass(FunderReadyBusinessPlanBuilder::class))
            ->getConstant('SECTION_MAP');
        $mappedKeys = collect($mapping)
            ->flatMap(fn (array $section): array => $section['keys'])
            ->unique()
            ->sort()
            ->values()
            ->all();
        $canonicalKeys = collect(PlanRequirements::definitions())
            ->flatMap(fn (array $phase): array => collect($phase['requirements'])->pluck('key')->all())
            ->sort()
            ->values()
            ->all();

        $this->assertSame($canonicalKeys, $mappedKeys);
        $this->assertSame([
            'Executive Summary',
            'Company Description',
            'Market Research',
            'Service Line & Pricing',
            'Organisation & Team',
            'Systems & Operations',
            'Legal, IP & Compliance',
            'Culture & Values',
            'Goals, Objectives & Milestones',
            'Finance',
        ], array_column($mapping, 'title'));
    }

    public function test_document_css_keeps_finance_and_internal_draft_controls_visible(): void
    {
        $builder = app(FunderReadyBusinessPlanBuilder::class);
        $method = new ReflectionMethod($builder, 'css');
        $method->setAccessible(true);
        $css = $method->invoke($builder);

        $this->assertStringContainsString('.funder-finance { break-before: page; }', $css);
        $this->assertStringContainsString('.funder-draft-watermark', $css);
        $this->assertStringContainsString('.funder-detail-table', $css);
    }
}
