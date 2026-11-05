<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class ActualSource implements \JsonSerializable
{
    /** @param array<string,list<string>>|null $accountMappings */
    public function __construct(public ?string $entityId, public ?string $provider, public ?string $badge, public ?bool $degraded,
        public ?string $periodStart, public ?string $periodEnd, public ?string $importVersion, public ?string $currency,
        public ?array $accountMappings, public ?string $reviewedBy, public ?string $reviewedOn, public ?string $reviewReference, public ?bool $reconciled)
    {
        foreach ([$periodStart, $periodEnd, $reviewedOn] as $date) {
            ForecastDates::read($date);
        }
        if ($provider !== null && ! in_array($provider, ['manual', 'xero'], true)) {
            throw new \InvalidArgumentException('Actuals must identify a documented manual or Xero source.');
        }
        foreach ($accountMappings ?? [] as $codes) {
            foreach ($codes as $code) {
                if (trim($code) === '') {
                    throw new \InvalidArgumentException('Account mappings cannot contain blank codes or document references.');
                }
            }
        }
    }

    /** @return array{entity_id:?string,provider:?string,badge:?string,degraded:?bool,period_start:?string,period_end:?string,import_version:?string,currency:?string,account_mappings:array<string,list<string>>|null,reviewed_by:?string,reviewed_on:?string,review_reference:?string,reconciled:?bool} */
    public function jsonSerialize(): array
    {
        return ['entity_id' => $this->entityId, 'provider' => $this->provider, 'badge' => $this->badge, 'degraded' => $this->degraded,
            'period_start' => $this->periodStart, 'period_end' => $this->periodEnd, 'import_version' => $this->importVersion,
            'currency' => $this->currency, 'account_mappings' => $this->accountMappings, 'reviewed_by' => $this->reviewedBy,
            'reviewed_on' => $this->reviewedOn, 'review_reference' => $this->reviewReference, 'reconciled' => $this->reconciled];
    }
}
