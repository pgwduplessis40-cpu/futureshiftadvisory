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

        $query = ServiceRateSetting::query()
            ->where('effective_from', '<=', now());

        if (Schema::hasColumn('service_rate_settings', 'is_active')) {
            $query->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhereNull('is_active');
            });
        }

        return $query
            ->latest('effective_from')
            ->latest()
            ->first();
    }

    public function currentHourlyRate(): float
    {
        if ($this->freeAccessModeActive()) {
            return 0.0;
        }

        return $this->current()?->hourly_rate ?? $this->fallbackHourlyRate();
    }

    /**
     * @return array{hourly_rate:float,base_hourly_rate:float,currency:string,rate_source:string,service_rate_setting_id:?string,effective_from:?string,npo_service_discount_percent:float,npo_discount_applied:bool,free_access_mode:bool}
     */
    public function currentRateSnapshot(bool $applyNpoServiceDiscount = false): array
    {
        if ($this->freeAccessModeActive()) {
            return [
                'hourly_rate' => 0.0,
                'base_hourly_rate' => 0.0,
                'currency' => $this->currency(),
                'rate_source' => 'fees_disabled',
                'service_rate_setting_id' => null,
                'effective_from' => null,
                'npo_service_discount_percent' => 0.0,
                'npo_discount_applied' => false,
                'free_access_mode' => true,
            ];
        }

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
                'free_access_mode' => false,
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
            'free_access_mode' => false,
        ];
    }

    /**
     * @return array{discount_percent:float,discount_rate:float,discount_source:string,service_rate_setting_id:?string,effective_from:?string,free_access_mode:bool}
     */
    public function npoRetainerDiscountSnapshot(): array
    {
        if ($this->freeAccessModeActive()) {
            return [
                'discount_percent' => 0.0,
                'discount_rate' => 0.0,
                'discount_source' => 'fees_disabled',
                'service_rate_setting_id' => null,
                'effective_from' => null,
                'free_access_mode' => true,
            ];
        }

        $setting = $this->current();
        $discountPercent = $this->npoRetainerDiscountPercent($setting);

        return [
            'discount_percent' => $discountPercent,
            'discount_rate' => round($discountPercent / 100, 4),
            'discount_source' => $setting instanceof ServiceRateSetting ? 'admin_service_rate' : 'config_fallback',
            'service_rate_setting_id' => $setting instanceof ServiceRateSetting ? (string) $setting->getKey() : null,
            'effective_from' => $setting instanceof ServiceRateSetting ? $setting->effective_from?->toIso8601String() : null,
            'free_access_mode' => false,
        ];
    }

    public function freeAccessModeActive(): bool
    {
        if (
            ! Schema::hasTable('service_rate_settings')
            || ! Schema::hasColumn('service_rate_settings', 'is_active')
            || ! Schema::hasColumn('service_rate_settings', 'free_access_enabled')
        ) {
            return false;
        }

        if ($this->current() instanceof ServiceRateSetting) {
            return false;
        }

        return ServiceRateSetting::query()
            ->where('free_access_enabled', true)
            ->where('effective_from', '<=', now())
            ->exists();
    }

    /**
     * @return array{free_access_mode:bool,charging_enabled:bool,current_rate_id:?string,current_rate_effective_from:?string,currency:string,manage_url:string}
     */
    public function chargingStatusPayload(): array
    {
        $current = $this->current();
        $freeAccessMode = $this->freeAccessModeActive();

        return [
            'free_access_mode' => $freeAccessMode,
            'charging_enabled' => ! $freeAccessMode,
            'current_rate_id' => $current instanceof ServiceRateSetting ? (string) $current->getKey() : null,
            'current_rate_effective_from' => $current instanceof ServiceRateSetting ? $current->effective_from?->toIso8601String() : null,
            'currency' => $this->currency(),
            'manage_url' => route('admin.service-rates.index', absolute: false),
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
        $attributes = [
            'hourly_rate' => max(0.0, round($hourlyRate, 2)),
            'currency' => $this->currency(),
            'npo_service_discount_percent' => $this->boundedPercent($npoServiceDiscountPercent),
            'npo_retainer_discount_percent' => $this->boundedPercent($npoRetainerDiscountPercent),
            'effective_from' => now(),
            'notes' => $notes,
            'created_by_user_id' => $actor->getKey(),
        ];

        if (Schema::hasColumn('service_rate_settings', 'is_active')) {
            $attributes['is_active'] = true;
        }

        if (Schema::hasColumn('service_rate_settings', 'free_access_enabled')) {
            $attributes['free_access_enabled'] = false;
            $attributes['free_access_enabled_at'] = null;
            $attributes['free_access_enabled_by_user_id'] = null;
        }

        $setting = ServiceRateSetting::query()->create($attributes);

        $this->audit->record('service_rate.updated', subject: $setting, actor: $actor, after: [
            'hourly_rate' => $setting->hourly_rate,
            'currency' => $setting->currency,
            'npo_service_discount_percent' => $setting->npo_service_discount_percent,
            'npo_retainer_discount_percent' => $setting->npo_retainer_discount_percent,
            'effective_from' => $setting->effective_from?->toIso8601String(),
            'is_active' => $setting->is_active !== false,
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
