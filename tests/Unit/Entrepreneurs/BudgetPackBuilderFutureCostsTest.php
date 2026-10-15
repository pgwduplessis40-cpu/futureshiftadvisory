<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Models\EntrepreneurBudget;
use App\Services\Entrepreneurs\BudgetPackBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BudgetPackBuilderFutureCostsTest extends TestCase
{
    public function test_scheduled_renewals_are_described_in_the_budget_pack_trace(): void
    {
        $budget = new EntrepreneurBudget([
            'future_costs' => [[
                'label' => 'Trademark renewal',
                'amount' => 85,
                'quantity' => 1,
                'year' => 10,
                'recurring' => false,
                'repeat_every_years' => 10,
                'classification' => 'operating',
                'confidence' => 'estimate',
            ]],
        ]);
        $builder = (new ReflectionClass(BudgetPackBuilder::class))
            ->newInstanceWithoutConstructor();
        $futureCosts = new \ReflectionMethod(
            BudgetPackBuilder::class,
            'futureCosts',
        );

        self::assertSame([
            [
                'label' => 'Trademark renewal',
                'amount' => 85.0,
                'timing' => 'From Year 10, every 10 years',
                'classification' => 'Operating cost',
                'confidence' => 'estimate',
            ],
        ], $futureCosts->invoke($builder, $budget));
    }
}
