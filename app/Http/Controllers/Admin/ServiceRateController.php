<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRateSetting;
use App\Models\User;
use App\Services\Fees\ServiceRateManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceRateController extends Controller
{
    public function __construct(private readonly ServiceRateManager $rates) {}

    public function index(): Response
    {
        $current = $this->rates->current();

        return Inertia::render('admin/service-rates/Index', [
            'current' => $current instanceof ServiceRateSetting
                ? $this->ratePayload($current)
                : null,
            'fallback' => [
                'hourly_rate' => $this->rates->fallbackHourlyRate(),
                'currency' => $this->rates->currency(),
                'npo_service_discount_percent' => $this->rates->fallbackNpoServiceDiscountPercent(),
                'npo_retainer_discount_percent' => $this->rates->fallbackNpoRetainerDiscountPercent(),
            ],
            'history' => $this->rates->recent()
                ->map(fn (ServiceRateSetting $setting): array => $this->ratePayload($setting))
                ->values(),
            'storeUrl' => route('admin.service-rates.store', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'npo_service_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'npo_retainer_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->rates->publish(
            hourlyRate: (float) $validated['hourly_rate'],
            npoServiceDiscountPercent: (float) $validated['npo_service_discount_percent'],
            npoRetainerDiscountPercent: (float) $validated['npo_retainer_discount_percent'],
            notes: isset($validated['notes']) ? trim((string) $validated['notes']) : null,
            actor: $user,
        );

        return to_route('admin.service-rates.index')->with('status', 'service-rate-updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function ratePayload(ServiceRateSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'hourly_rate' => $setting->hourly_rate,
            'currency' => $setting->currency,
            'npo_service_discount_percent' => $setting->npo_service_discount_percent,
            'npo_retainer_discount_percent' => $setting->npo_retainer_discount_percent,
            'effective_from' => $setting->effective_from?->toIso8601String(),
            'notes' => $setting->notes,
            'created_by_name' => $setting->createdBy?->name,
            'created_at' => $setting->created_at?->toIso8601String(),
        ];
    }
}
