<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * @phpstan-type StreamResult array{id:string,name:string,unit:?string,in_base_case:?bool,evidence_status:?string,booked_units:?string,requested_delivery_units:?string,invoiced_units:?string,founder_capacity_units:?string,total_capacity_units:?string,delivered_units:?string,founder_hours_used:?string,contractor_units:?string,revenue:?string,contractor_cost:?string,missing:list<string>,errors:list<string>}
 */
final class StreamProjection implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.driver_revenue'];
    }

    /** @return StreamResult */
    public function calculate(RevenueStream $stream, ?StreamMonth $month): array
    {
        $missing = [];
        $errors = [];
        foreach (['unit' => $stream->unit, 'price' => $stream->price, 'preparation_hours_per_unit' => $stream->preparationHoursPerUnit,
            'delivery_hours_per_unit' => $stream->deliveryHoursPerUnit, 'in_base_case' => $stream->inBaseCase, 'evidence_status' => $stream->evidenceStatus,
            'booked_units' => $month?->bookedUnits, 'delivery_units' => $month?->deliveryUnits, 'invoiced_units' => $month?->invoicedUnits,
            'founder_hours' => $month?->founderHours, 'additional_capacity_units' => $month?->additionalCapacityUnits] as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = $key;
            }
        }
        $prep = DriverNumbers::read($stream->preparationHoursPerUnit);
        $delivery = DriverNumbers::read($stream->deliveryHoursPerUnit);
        $hours = $prep !== null && $delivery !== null ? $prep->plus($delivery) : null;
        if ($hours?->isZero()) {
            $errors[] = 'hours_per_unit_must_be_positive';
        }
        $allocated = DriverNumbers::read($month?->founderHours);
        $founderCapacity = $hours !== null && ! $hours->isZero() && $allocated !== null
            ? $allocated->dividedBy($hours, 8, RoundingMode::Down) : null;
        $additional = DriverNumbers::read($month?->additionalCapacityUnits);
        $providerCost = DriverNumbers::read($month?->providerUnitCost);
        $providerMissing = [];
        if ($additional !== null && ! $additional->isZero()) {
            if (trim($month->provider ?? '') === '') {
                $providerMissing[] = 'provider';
            }
            if ($providerCost === null) {
                $providerMissing[] = 'provider_unit_cost';
            } elseif ($providerCost->isZero() && trim($month->noCostBasis ?? '') === '') {
                $providerMissing[] = 'no_cost_basis';
            }
        }
        $missing = [...$missing, ...$providerMissing];
        $capacity = $founderCapacity !== null && $additional !== null && $providerMissing === []
            ? $founderCapacity->plus($additional) : null;
        $requested = DriverNumbers::read($month?->deliveryUnits);
        $units = $capacity !== null && $requested !== null ? BigDecimal::min($capacity, $requested) : null;
        $founderUnits = $units !== null ? BigDecimal::min($units, $founderCapacity) : null;
        $contractorUnits = $units !== null && $founderUnits !== null ? $units->minus($founderUnits) : null;
        $price = DriverNumbers::read($stream->price);
        $revenue = $units !== null && $price !== null && trim($stream->unit ?? '') !== '' ? $units->multipliedBy($price) : null;
        $cost = $contractorUnits?->isZero() ? BigDecimal::zero()
            : ($contractorUnits !== null && $providerCost !== null ? $contractorUnits->multipliedBy($providerCost) : null);

        return ['id' => $stream->id, 'name' => $stream->name, 'unit' => $stream->unit, 'in_base_case' => $stream->inBaseCase,
            'evidence_status' => $stream->evidenceStatus, 'booked_units' => $month?->bookedUnits,
            'requested_delivery_units' => $month?->deliveryUnits, 'invoiced_units' => $month?->invoicedUnits,
            'founder_capacity_units' => $founderCapacity === null ? null : (string) $founderCapacity,
            'total_capacity_units' => $capacity === null ? null : (string) $capacity,
            'delivered_units' => $units === null ? null : (string) $units,
            'founder_hours_used' => $founderUnits !== null ? (string) $founderUnits->multipliedBy($hours) : null,
            'contractor_units' => $contractorUnits === null ? null : (string) $contractorUnits,
            'revenue' => $revenue === null ? null : DriverNumbers::money($revenue),
            'contractor_cost' => $cost === null ? null : DriverNumbers::money($cost), 'missing' => $missing, 'errors' => $errors];
    }
}
