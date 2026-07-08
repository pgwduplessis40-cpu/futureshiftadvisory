<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRatePackage;
use App\Models\ServiceRateSetting;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Fees\ServiceRateManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceRateController extends Controller
{
    public function __construct(
        private readonly ServiceRateManager $rates,
        private readonly AuditWriter $audit,
    ) {}

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
            'packages' => ServiceRatePackage::query()
                ->with('createdBy')
                ->latest('is_active')
                ->orderBy('service_type')
                ->orderBy('purchase_price_min')
                ->orderByDesc('effective_from')
                ->get()
                ->map(fn (ServiceRatePackage $package): array => $this->packagePayload($package))
                ->values(),
            'dueDiligencePackageScopes' => ServiceRatePackage::dueDiligencePackageScopeOptions(),
            'entrepreneurPackageScopes' => ServiceRatePackage::entrepreneurPackageScopeOptions(),
            'packageStoreUrl' => route('admin.service-rates.packages.store', absolute: false),
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

    public function toggle(Request $request, ServiceRateSetting $serviceRateSetting): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'free_access_acknowledged' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $isActive = (bool) $validated['is_active'];
        $enablesFreeAccess = $this->deactivationLeavesNoCurrentRate($serviceRateSetting, $isActive);

        if ($enablesFreeAccess && ! (bool) ($validated['free_access_acknowledged'] ?? false)) {
            throw ValidationException::withMessages([
                'free_access_acknowledged' => 'Deactivating the last active service rate enables free-access mode. Confirm this explicitly before continuing.',
            ]);
        }

        $before = ['is_active' => $serviceRateSetting->is_active];
        $serviceRateSetting->forceFill([
            'is_active' => $isActive,
            'free_access_enabled' => $enablesFreeAccess,
            'free_access_enabled_at' => $enablesFreeAccess ? now() : null,
            'free_access_enabled_by_user_id' => $enablesFreeAccess ? $user->getKey() : null,
        ])->save();

        $this->audit->record('service_rate.toggled', subject: $serviceRateSetting, actor: $user, before: $before, after: [
            'is_active' => $serviceRateSetting->is_active,
            'free_access_enabled' => $serviceRateSetting->free_access_enabled,
        ]);

        return to_route('admin.service-rates.index')->with('status', 'service-rate-updated');
    }

    public function storePackage(Request $request): RedirectResponse
    {
        $validated = $this->validatePackage($request);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $package = ServiceRatePackage::query()->create([
            ...$this->packageAttributes($validated),
            'currency' => $this->rates->currency(),
            'effective_from' => now(),
            'created_by_user_id' => $user->getKey(),
        ]);

        $this->audit->record('service_rate_package.created', subject: $package, actor: $user, after: $package->snapshot());

        return to_route('admin.service-rates.index')->with('status', 'service-rate-package-created');
    }

    public function updatePackage(Request $request, ServiceRatePackage $serviceRatePackage): RedirectResponse
    {
        $validated = $this->validatePackage($request);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $before = [
            ...$serviceRatePackage->snapshot(),
            'is_active' => $serviceRatePackage->is_active,
            'effective_to' => $serviceRatePackage->effective_to?->toIso8601String(),
        ];

        $attributes = $this->packageAttributes($validated);
        $isActive = (bool) $attributes['is_active'];
        $serviceRatePackage->forceFill([
            ...$attributes,
            'effective_to' => $isActive
                ? null
                : ($serviceRatePackage->effective_to ?? now()),
        ])->save();

        $this->audit->record('service_rate_package.updated', subject: $serviceRatePackage, actor: $user, before: $before, after: [
            ...$serviceRatePackage->snapshot(),
            'is_active' => $serviceRatePackage->is_active,
            'effective_to' => $serviceRatePackage->effective_to?->toIso8601String(),
        ]);

        return to_route('admin.service-rates.index')->with('status', 'service-rate-package-updated');
    }

    public function togglePackage(Request $request, ServiceRatePackage $serviceRatePackage): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $before = ['is_active' => $serviceRatePackage->is_active];
        $serviceRatePackage->forceFill([
            'is_active' => (bool) $validated['is_active'],
            'effective_to' => (bool) $validated['is_active'] ? null : now(),
        ])->save();

        $this->audit->record('service_rate_package.toggled', subject: $serviceRatePackage, actor: $user, before: $before, after: [
            'is_active' => $serviceRatePackage->is_active,
            'effective_to' => $serviceRatePackage->effective_to?->toIso8601String(),
        ]);

        return to_route('admin.service-rates.index')->with('status', 'service-rate-package-updated');
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
            'is_active' => $setting->is_active !== false,
            'free_access_enabled' => $setting->free_access_enabled === true,
            'free_access_enabled_at' => $setting->free_access_enabled_at?->toIso8601String(),
            'notes' => $setting->notes,
            'created_by_name' => $setting->createdBy?->name,
            'created_at' => $setting->created_at?->toIso8601String(),
            'toggle_url' => route('admin.service-rates.toggle', $setting, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packagePayload(ServiceRatePackage $package): array
    {
        return [
            ...$package->snapshot(),
            'is_active' => $package->is_active,
            'effective_to' => $package->effective_to?->toIso8601String(),
            'created_by_name' => $package->createdBy?->name,
            'created_at' => $package->created_at?->toIso8601String(),
            'update_url' => route('admin.service-rates.packages.update', $package, absolute: false),
            'toggle_url' => route('admin.service-rates.packages.toggle', $package, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePackage(Request $request): array
    {
        return $request->validate([
            'service_type' => ['required', Rule::in([
                ServiceRatePackage::SERVICE_DUE_DILIGENCE,
                ServiceRatePackage::SERVICE_ENTREPRENEUR,
            ])],
            'package_scope' => ['nullable', 'string', Rule::in(ServiceRatePackage::packageScopes())],
            'package_name' => ['required', 'string', 'max:160'],
            'client_label' => ['required', 'string', 'max:160'],
            'billing_model' => ['required', Rule::in([
                ServiceRatePackage::BILLING_FIXED_FEE,
                ServiceRatePackage::BILLING_HOURLY_RETAINER,
                ServiceRatePackage::BILLING_PROPOSAL,
            ])],
            'fixed_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'deposit_percent' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'retainer_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'purchase_price_min' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'purchase_price_max' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'gte:purchase_price_min'],
            'scope_description' => ['required', 'string', 'min:20', 'max:4000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function packageAttributes(array $validated): array
    {
        $serviceType = (string) $validated['service_type'];
        $packageScope = match ($serviceType) {
            ServiceRatePackage::SERVICE_DUE_DILIGENCE => ServiceRatePackage::normaliseDueDiligenceScope(
                (string) ($validated['package_scope'] ?? ''),
                $validated['purchase_price_min'] ?? null,
                $validated['purchase_price_max'] ?? null,
                (string) $validated['package_name'],
                (string) $validated['client_label'],
            ),
            ServiceRatePackage::SERVICE_ENTREPRENEUR => ServiceRatePackage::normaliseEntrepreneurScope((string) ($validated['package_scope'] ?? '')),
            default => null,
        };

        return [
            'service_type' => $serviceType,
            'package_scope' => $packageScope,
            'package_name' => trim((string) $validated['package_name']),
            'client_label' => trim((string) $validated['client_label']),
            'billing_model' => $validated['billing_model'],
            'fixed_fee' => $validated['fixed_fee'] ?? null,
            'deposit_percent' => $validated['deposit_percent'] ?? 100,
            'hourly_rate' => $validated['hourly_rate'] ?? null,
            'retainer_amount' => $validated['retainer_amount'] ?? null,
            'purchase_price_min' => $validated['purchase_price_min'] ?? null,
            'purchase_price_max' => $validated['purchase_price_max'] ?? null,
            'scope_description' => trim((string) $validated['scope_description']),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }

    private function deactivationLeavesNoCurrentRate(ServiceRateSetting $setting, bool $nextActive): bool
    {
        if ($nextActive || $setting->is_active === false) {
            return false;
        }

        $eligibleActiveRates = ServiceRateSetting::query()
            ->where('effective_from', '<=', now())
            ->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhereNull('is_active');
            })
            ->count();

        return $eligibleActiveRates <= 1
            && (string) $this->rates->current()?->getKey() === (string) $setting->getKey();
    }
}
