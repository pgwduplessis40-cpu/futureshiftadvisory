<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;

final readonly class TaxCashPlan implements \JsonSerializable, ProvidesMethodology
{
    /** @param list<CashMovement> $movements */
    public function __construct(public ?string $taxState, public ?string $gstState, public ?string $taxSource, public ?string $gstSource, public array $movements)
    {
        foreach ([$taxState, $gstState] as $state) {
            if ($state !== null && ! in_array($state, ['scheduled', 'none_due', 'not_applicable'], true)) {
                throw new \InvalidArgumentException('Tax/GST cash needs an explicit schedule or documented scope state.');
            }
        }
        foreach ($movements as $movement) {
            if (! in_array($movement->category, ['tax', 'gst'], true)) {
                throw new \InvalidArgumentException('A tax/GST cash plan cannot contain other cash categories.');
            }
        }
    }

    public static function methodologyIds(): array
    {
        return ['entrepreneur.assets_tax_actuals'];
    }

    /** @return array{missing:list<string>,errors:list<string>} */
    public function issues(): array
    {
        $missing = $errors = [];
        foreach (['tax' => [$this->taxState, $this->taxSource], 'gst' => [$this->gstState, $this->gstSource]] as $category => [$state, $source]) {
            if ($state === null || trim($source ?? '') === '') {
                $missing[] = $category.'.scope_or_source';
            }
            $entries = array_filter($this->movements, fn (CashMovement $m): bool => $m->category === $category);
            if ($state === 'scheduled' && $entries === []) {
                $missing[] = $category.'.schedule';
            } elseif (in_array($state, ['none_due', 'not_applicable'], true) && $entries !== []) {
                $errors[] = $category.'.schedule_contradicts_scope';
            }
            foreach ($entries as $entry) {
                if ($entry->date === null || $entry->amount === null) {
                    $missing[] = $entry->id.'.date_or_amount';
                }
                if ($category === 'tax' && $entry->direction === 'in' && $entry->verified !== true) {
                    $missing[] = $entry->id.'.refund_verification';
                }
            }
        }

        return ['missing' => $missing, 'errors' => $errors];
    }

    /** @return array{tax_state:?string,gst_state:?string,tax_source:?string,gst_source:?string,movements:list<CashMovement>} */
    public function jsonSerialize(): array
    {
        return ['tax_state' => $this->taxState, 'gst_state' => $this->gstState, 'tax_source' => $this->taxSource,
            'gst_source' => $this->gstSource, 'movements' => $this->movements];
    }
}
