<?php

declare(strict_types=1);

namespace App\Services\Fees;

use App\Models\ServiceRateSetting;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class ServiceRateManager
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function current(): ?ServiceRateSetting
    {
        if (! Schema::hasTable('service_rate_settings')) {
            return null;
        }

        return ServiceRateSetting::query()
            ->where('effective_from', '<=', now())
            ->latest('effective_from')
            ->latest()
            ->first();
    }

    public function currentHourlyRate(): float
    {
        return $this->current()?->hourly_rate ?? $this->fallbackHourlyRate();
    }

    /**
     * @return array{hourly_rate:float,base_hourly_rate:float,currency:string,rate_source:string,service_rate_setting_id:?string,effective_from:?string,npo_service_discount_percent:float,npo_discount_applied:bool}
     */
    public function currentRateSnapshot(bool $applyNpoServiceDiscount = false): array
    {
        $setting = $this->current();
        $baseHourlyRate = $setting instanceof ServiceRateSetting
            ? max(0.0, round($setting->hourly_rate, 2))
            : $this->fallbackHourlyRate();
        $discountPercent = $applyNpoServiceDiscount
            ? $this->npoServiceDiscountPercent($setting)
            : 0.0;
        $hourlyRate = round($baseHourlyRate * (1 - ($discountPercent / 100)), 2);

        if ($setting instanceof ServiceRateSetting) {
            return [
                'hourly_rate' => $hourlyRate,
                'base_hourly_rate' => $baseHourlyRate,
                'currency' => $setting->currency,
                'rate_source' => 'admin_service_rate',
                'service_rate_setting_id' => (string) $setting->getKey(),
                'effective_from' => $setting->effective_from?->toIso8601String(),
                'npo_service_discount_percent' => $discountPercent,
                'npo_discount_applied' => $applyNpoServiceDiscount && $discountPercent > 0.0,
            ];
        }

        return [
            'hourly_rate' => $hourlyRate,
            'base_hourly_rate' => $baseHourlyRate,
            'currency' => $this->currency(),
            'rate_source' => 'config_fallback',
            'service_rate_setting_id' => null,
            'effective_from' => null,
            'npo_service_discount_percent' => $discountPercent,
            'npo_discount_applied' => $applyNpoServiceDiscount && $discountPercent > 0.0,
        ];
    }

    /**
     * @return array{discount_percent:float,discount_rate:float,discount_source:string,service_rate_setting_id:?string,effective_from:?string}
     */
    public function npoRetainerDiscountSnapshot(): array
    {
        $setting = $this->current();
        $discountPercent = $this->npoRetainerDiscountPercent($setting);

        return [
            'discount_percent' => $discountPercent,
            'discount_rate' => round($discountPercent / 100, 4),
            'discount_source' => $setting instanceof ServiceRateSetting ? 'admin_service_rate' : 'config_fallback',
            'service_rate_setting_id' => $setting instanceof ServiceRateSetting ? (string) $setting->getKey() : null,
            'effective_from' => $setting instanceof ServiceRateSetting ? $setting->effective_from?->toIso8601String() : null,
        ];
    }

    public function fallbackHourlyRate(): float
    {
        return max(0.0, round((float) config('fees.service.default_hourly_rate', 250), 2));
    }

    public function currency(): string
    {
        return (string) config('fees.service.currency', 'NZD');
    }

    public function fallbackNpoServiceDiscountPercent(): float
    {
        return $this->boundedPercent(config('fees.npo.service_rate_discount_percent', 30));
    }

    public function fallbackNpoRetainerDiscountPercent(): float
    {
        return $this->boundedPercent(config('fees.npo.retainer_discount_percent', 35));
    }

    public function publish(
        float $hourlyRate,
        float $npoServiceDiscountPercent,
        float $npoRetainerDiscountPercent,
        ?string $notes,
        User $actor,
    ): ServiceRateSetting {
        $setting = ServiceRateSetting::query()->create([
            'hourly_rate' => max(0.0, round($hourlyRate, 2)),
            'currency' => $this->currency(),
            'npo_service_discount_percent' => $this->boundedPercent($npoServiceDiscountPercent),
            'npo_retainer_discount_percent' => $this->boundedPercent($npoRetainerDiscountPercent),
            'effective_from' => now(),
            'notes' => $notes,
            'created_by_user_id' => $actor->getKey(),
        ]);

        $this->audit->record('service_rate.updated', subject: $setting, actor: $actor, after: [
            'hourly_rate' => $setting->hourly_rate,
            'currency' => $setting->currency,
            'npo_service_discount_percent' => $setting->npo_service_discount_percent,
            'npo_retainer_discount_percent' => $setting->npo_retainer_discount_percent,
            'effective_from' => $setting->effective_from?->toIso8601String(),
        ]);

        return $setting->refresh();
    }

    /**
     * @return Collection<int, ServiceRateSetting>
     */
    public function recent(): Collection
    {
        if (! Schema::hasTable('service_rate_settings')) {
            return collect();
        }

        return ServiceRateSetting::query()
            ->with('createdBy')
            ->latest('effective_from')
            ->latest()
            ->limit(20)
            ->get();
    }

    private function npoServiceDiscountPercent(?ServiceRateSetting $setting): float
    {
        if ($setting instanceof ServiceRateSetting && is_numeric($setting->npo_service_discount_percent)) {
            return $this->boundedPercent($setting->npo_service_discount_percent);
        }

        return $this->fallbackNpoServiceDiscountPercent();
    }

    private function npoRetainerDiscountPercent(?ServiceRateSetting $setting): float
    {
        if ($setting instanceof ServiceRateSetting && is_numeric($setting->npo_retainer_discount_percent)) {
            return $this->boundedPercent($setting->npo_retainer_discount_percent);
        }

        return $this->fallbackNpoRetainerDiscountPercent();
    }

    private function boundedPercent(mixed $value): float
    {
        return max(0.0, min(100.0, round((float) $value, 2)));
    }
}
