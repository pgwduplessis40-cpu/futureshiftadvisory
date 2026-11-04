<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\ReleaseGate\Audience;
use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\FactSet;
use App\Services\Entrepreneurs\ReleaseGate\SourceReference;
use App\Services\Entrepreneurs\ReleaseGate\SourceVersion;

/** Independent synthetic numbers; this is not a reconstruction of F1 or F2. */
final class SyntheticCase
{
    public static function fact(string $key, string|int|bool|null $value, string $entity = 'business', string $period = 'current', string $scenario = 'base', ?string $unit = 'NZD', ?string $basis = null, ?string $date = '2026-11-01'): Fact
    {
        return new Fact($key, $value, $entity, $period, $scenario, $unit, $basis, $date, true, [new SourceReference('synthetic', 'complete-case', $key, hash('sha256', 'independent-case-v1'))]);
    }

    /** @param list<Fact> $facts */
    public static function context(array $facts, int $required = 2): DocumentContext
    {
        $hash = CanonicalJson::hash($facts);

        return new DocumentContext(new SourceVersion('synthetic', $hash, $hash, [], $required, '2026-11-01', '{}'), new FactSet($facts), 'budget', Audience::External);
    }

    /** @return list<Fact> */
    public static function cash(): array
    {
        $f = self::fact(...);
        $facts = [$f('model.horizon', 2, unit: 'months'), $f('model.opening_cash', '1000', basis: 'bank-A:unrestricted'),
            $f('model.available_funds', '1200'), $f('model.funding_total', '200'), $f('funding.inventory_complete', true),
            $f('funding.amount', '200', 'capital-A'), $f('funding.identity', 'capital-A', 'capital-A'),
            $f('funding.type', 'owner_capital', 'capital-A'), $f('funding.commitment', 'committed', 'capital-A'),
            $f('funding.availability', '2026-11-01', 'capital-A'), $f('funding.draw_date', '2026-11-01', 'capital-A'),
            $f('funding.restriction', 'unrestricted', 'capital-A'), $f('ledger.funding_identity', 'capital-A', 'capital-draw', 'month:1'),
            $f('cash.annual_movement', '200', period: 'year:1'), $f('cash.annual_closing', '1200', period: 'year:1')];
        foreach ([1 => ['1000', '150', '1150', '2026-11-01'], 2 => ['1150', '50', '1200', '2026-12-01']] as $m => [$opening, $movement, $closing, $date]) {
            foreach (['cash.opening' => $opening, 'cash.movement' => $movement, 'cash.closing' => $closing, 'cash.period_start' => $date, 'ledger.coverage' => 'complete'] as $key => $value) {
                $facts[] = $f($key, $value, period: 'month:'.$m);
            }
        }
        foreach ([['capital-draw', 1, '200', 'financing_inflows', '2026-11-01'], ['cost-1', 1, '50', 'operating_payments', '2026-11-03'], ['receipt-2', 2, '100', 'customer_receipts', '2026-12-02'], ['cost-2', 2, '50', 'operating_payments', '2026-12-03']] as [$id, $month, $amount, $category, $date]) {
            foreach (['ledger.amount' => $amount, 'ledger.category' => $category, 'ledger.date' => $date, 'ledger.source' => $id] as $key => $value) {
                $facts[] = $f($key, $value, $id, 'month:'.$month);
            }
        }

        return $facts;
    }

    /** @param list<Fact> $facts @return list<Fact> */
    public static function replace(array $facts, string $key, string|int|bool|null $value, ?string $entity = null, ?string $period = null): array
    {
        return array_map(fn (Fact $f): Fact => $f->key === $key && ($entity === null || $f->entity === $entity) && ($period === null || $f->period === $period)
            ? new Fact($f->key, $value, $f->entity, $f->period, $f->scenario, $f->unit, $f->basis, $f->asOf, $f->confirmed, $f->sources) : $f, $facts);
    }
}
