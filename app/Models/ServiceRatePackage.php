<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ServiceRatePackage extends Model
{
    use HasUuids;

    public const SERVICE_DUE_DILIGENCE = 'due_diligence';

    public const SERVICE_ENTREPRENEUR = 'entrepreneur';

    public const BILLING_FIXED_FEE = 'fixed_fee';

    public const BILLING_HOURLY_RETAINER = 'hourly_retainer';

    public const BILLING_PROPOSAL = 'proposal';

    public const SCOPE_ENTREPRENEUR_IDEA_VALIDATION = 'idea_validation';

    public const SCOPE_ENTREPRENEUR_PLAN_BUDGET = 'plan_budget';

    public const SCOPE_ENTREPRENEUR_COMBO = 'combo';

    public const SCOPE_DD_UNDER_300K = 'dd_under_300k';

    public const SCOPE_DD_300K_1M = 'dd_300k_1m';

    public const SCOPE_DD_1M_3M = 'dd_1m_3m';

    protected $guarded = [];

    protected $casts = [
        'fixed_fee' => 'float',
        'deposit_percent' => 'float',
        'hourly_rate' => 'float',
        'retainer_amount' => 'float',
        'purchase_price_min' => 'float',
        'purchase_price_max' => 'float',
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, ServiceRatePackage>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<ServiceActivation>
     */
    public function serviceActivations(): HasMany
    {
        return $this->hasMany(ServiceActivation::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'service_type' => $this->service_type,
            'package_scope' => $this->packageScope(),
            'package_name' => $this->package_name,
            'client_label' => $this->client_label,
            'billing_model' => $this->billing_model,
            'fixed_fee' => $this->fixed_fee,
            'deposit_percent' => $this->depositPercent(),
            'hourly_rate' => $this->hourly_rate,
            'retainer_amount' => $this->retainer_amount,
            'purchase_price_min' => $this->purchase_price_min,
            'purchase_price_max' => $this->purchase_price_max,
            'currency' => $this->currency,
            'scope_description' => $this->scope_description,
            'included_stages' => $this->includedStages(),
            'client_outcomes' => $this->clientOutcomes(),
            'access' => $this->accessPayload(),
            'payment_split' => $this->paymentSplit(),
            'effective_from' => $this->effective_from?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function entrepreneurPackageScopes(): array
    {
        return [
            self::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            self::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
            self::SCOPE_ENTREPRENEUR_COMBO,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function dueDiligencePackageScopes(): array
    {
        return [
            self::SCOPE_DD_UNDER_300K,
            self::SCOPE_DD_300K_1M,
            self::SCOPE_DD_1M_3M,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function packageScopes(): array
    {
        return [
            ...self::entrepreneurPackageScopes(),
            ...self::dueDiligencePackageScopes(),
        ];
    }

    /**
     * @return array<int, array{value:string,label:string,description:string}>
     */
    public static function entrepreneurPackageScopeOptions(): array
    {
        return [
            [
                'value' => self::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
                'label' => 'Idea Validation',
                'description' => 'Client completes concept validation and receives advisor gate feedback before plan work.',
            ],
            [
                'value' => self::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
                'label' => 'Business Plan + Budget',
                'description' => 'Client starts directly in the business plan and budget builder.',
            ],
            [
                'value' => self::SCOPE_ENTREPRENEUR_COMBO,
                'label' => 'Idea + Business Plan + Budget',
                'description' => 'Client validates the idea first, then unlocks the plan and budget after advisor approval.',
            ],
        ];
    }

    /**
     * @return array<int, array{value:string,label:string,description:string}>
     */
    public static function dueDiligencePackageScopeOptions(): array
    {
        return [
            [
                'value' => self::SCOPE_DD_UNDER_300K,
                'label' => 'Purchase price below $300k',
                'description' => 'Fixed-fee DD workspace for smaller acquisitions with purchase price up to $300k.',
            ],
            [
                'value' => self::SCOPE_DD_300K_1M,
                'label' => 'Purchase price $300k-$1m',
                'description' => 'Fixed-fee DD workspace for mid-market acquisitions between $300k and $1m.',
            ],
            [
                'value' => self::SCOPE_DD_1M_3M,
                'label' => 'Purchase price $1m-$3m',
                'description' => 'Fixed-fee DD workspace for larger acquisitions between $1m and $3m.',
            ],
        ];
    }

    public static function packageScopeLabel(?string $scope): string
    {
        return match ($scope) {
            self::SCOPE_ENTREPRENEUR_IDEA_VALIDATION => 'Idea Validation',
            self::SCOPE_ENTREPRENEUR_PLAN_BUDGET => 'Business Plan + Budget',
            self::SCOPE_ENTREPRENEUR_COMBO => 'Idea + Business Plan + Budget',
            self::SCOPE_DD_UNDER_300K => 'Purchase price below $300k',
            self::SCOPE_DD_300K_1M => 'Purchase price $300k-$1m',
            self::SCOPE_DD_1M_3M => 'Purchase price $1m-$3m',
            default => 'Standard workspace',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function accessFor(?string $serviceType, ?string $scope): array
    {
        if ($serviceType === self::SERVICE_DUE_DILIGENCE) {
            $scope = self::normaliseDueDiligenceScope($scope);

            return [
                'package_scope' => $scope,
                'package_scope_label' => self::packageScopeLabel($scope),
                'includes_idea_validation' => false,
                'includes_plan_budget' => false,
                'included_stages' => self::includedStagesFor($scope),
                'client_outcomes' => self::clientOutcomesFor($scope),
            ];
        }

        if ($serviceType !== self::SERVICE_ENTREPRENEUR) {
            return [
                'package_scope' => $scope,
                'package_scope_label' => self::packageScopeLabel($scope),
                'includes_idea_validation' => false,
                'includes_plan_budget' => false,
                'included_stages' => [],
                'client_outcomes' => [],
            ];
        }

        $scope = self::normaliseEntrepreneurScope($scope);
        $includesIdea = in_array($scope, [
            self::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            self::SCOPE_ENTREPRENEUR_COMBO,
        ], true);
        $includesPlan = in_array($scope, [
            self::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
            self::SCOPE_ENTREPRENEUR_COMBO,
        ], true);

        return [
            'package_scope' => $scope,
            'package_scope_label' => self::packageScopeLabel($scope),
            'includes_idea_validation' => $includesIdea,
            'includes_plan_budget' => $includesPlan,
            'included_stages' => self::includedStagesFor($scope),
            'client_outcomes' => self::clientOutcomesFor($scope),
        ];
    }

    public static function normaliseEntrepreneurScope(?string $scope): string
    {
        return in_array($scope, self::entrepreneurPackageScopes(), true)
            ? (string) $scope
            : self::SCOPE_ENTREPRENEUR_COMBO;
    }

    public static function normaliseDueDiligenceScope(
        ?string $scope,
        mixed $purchasePriceMin = null,
        mixed $purchasePriceMax = null,
        ?string $packageName = null,
        ?string $clientLabel = null,
    ): string {
        if (in_array($scope, self::dueDiligencePackageScopes(), true)) {
            return (string) $scope;
        }

        $text = strtolower(trim(($packageName ?? '').' '.($clientLabel ?? '')));
        if (str_contains($text, '300k') && (str_contains($text, 'below') || str_contains($text, 'up to'))) {
            return self::SCOPE_DD_UNDER_300K;
        }

        if (str_contains($text, '300k') && str_contains($text, '1m')) {
            return self::SCOPE_DD_300K_1M;
        }

        if (str_contains($text, '1m') && str_contains($text, '3m')) {
            return self::SCOPE_DD_1M_3M;
        }

        $min = $purchasePriceMin !== null ? (float) $purchasePriceMin : null;
        $max = $purchasePriceMax !== null ? (float) $purchasePriceMax : null;

        if ($max !== null && $max <= 300000.0) {
            return self::SCOPE_DD_UNDER_300K;
        }

        if ($min !== null && $min >= 1000000.0) {
            return self::SCOPE_DD_1M_3M;
        }

        if (($min !== null && $min >= 300000.0) || ($max !== null && $max <= 1000000.0)) {
            return self::SCOPE_DD_300K_1M;
        }

        return self::SCOPE_DD_300K_1M;
    }

    public function packageScope(): ?string
    {
        if ($this->service_type === self::SERVICE_DUE_DILIGENCE) {
            return self::normaliseDueDiligenceScope(
                $this->package_scope,
                $this->purchase_price_min,
                $this->purchase_price_max,
                $this->package_name,
                $this->client_label,
            );
        }

        if ($this->service_type === self::SERVICE_ENTREPRENEUR) {
            return self::normaliseEntrepreneurScope($this->package_scope);
        }

        return $this->package_scope;
    }

    /**
     * @return array<int, string>
     */
    public function includedStages(): array
    {
        return self::includedStagesFor($this->packageScope());
    }

    /**
     * @return array<int, string>
     */
    public function clientOutcomes(): array
    {
        return self::clientOutcomesFor($this->packageScope());
    }

    /**
     * @return array<string, mixed>
     */
    public function accessPayload(): array
    {
        return self::accessFor($this->service_type, $this->packageScope());
    }

    public function depositPercent(): float
    {
        if ($this->billing_model !== self::BILLING_FIXED_FEE || (float) ($this->fixed_fee ?? 0) <= 0) {
            return 100.0;
        }

        return min(max((float) ($this->deposit_percent ?? 100), 0.0), 100.0);
    }

    /**
     * @return array{deposit_percent:float,card_deposit_amount:float|null,bank_transfer_amount:float|null,requires_bank_transfer:bool}
     */
    public function paymentSplit(): array
    {
        if ($this->billing_model !== self::BILLING_FIXED_FEE || $this->fixed_fee === null) {
            return [
                'deposit_percent' => $this->depositPercent(),
                'card_deposit_amount' => null,
                'bank_transfer_amount' => null,
                'requires_bank_transfer' => false,
            ];
        }

        $fixedFee = (float) $this->fixed_fee;
        $cardDeposit = round($fixedFee * ($this->depositPercent() / 100), 2);
        $bankTransfer = round(max($fixedFee - $cardDeposit, 0), 2);

        return [
            'deposit_percent' => $this->depositPercent(),
            'card_deposit_amount' => $cardDeposit,
            'bank_transfer_amount' => $bankTransfer,
            'requires_bank_transfer' => $bankTransfer > 0,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function includedStagesFor(?string $scope): array
    {
        return match ($scope) {
            self::SCOPE_ENTREPRENEUR_IDEA_VALIDATION => [
                'Idea validation questionnaire',
                'AI-supported viability review',
                'Advisor gate feedback',
            ],
            self::SCOPE_ENTREPRENEUR_PLAN_BUDGET => [
                'Business plan workspace',
                'Section-by-section plan builder',
                'Budget and runway builder',
                'Advisor assessment',
            ],
            self::SCOPE_ENTREPRENEUR_COMBO => [
                'Idea validation questionnaire',
                'Advisor gate feedback',
                'Business plan workspace',
                'Budget and runway builder',
                'Advisor assessment',
            ],
            self::SCOPE_DD_UNDER_300K, self::SCOPE_DD_300K_1M, self::SCOPE_DD_1M_3M => [
                'Acquisition target intake and purchase band confirmation',
                'Due diligence questionnaire and document upload workspace',
                'Acquisition plan builder with advisor guidance',
                'Advisor review, risk signals, and next-step decision support',
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private static function clientOutcomesFor(?string $scope): array
    {
        return match ($scope) {
            self::SCOPE_ENTREPRENEUR_IDEA_VALIDATION => [
                'Clear evidence of whether the idea is ready to progress.',
                'Practical advisor feedback on problem, customer, demand, and revenue logic.',
            ],
            self::SCOPE_ENTREPRENEUR_PLAN_BUDGET => [
                'A structured business plan that can be assessed by the advisor.',
                'A working budget, runway view, and funding assumptions to support launch decisions.',
            ],
            self::SCOPE_ENTREPRENEUR_COMBO => [
                'A validated concept before detailed planning starts.',
                'A structured business plan and budget after advisor approval unlocks the builder.',
            ],
            self::SCOPE_DD_UNDER_300K, self::SCOPE_DD_300K_1M, self::SCOPE_DD_1M_3M => [
                'A structured acquisition plan for the target business.',
                'Clearer risk, evidence, and purchase-decision signals before committing further.',
            ],
            default => [],
        };
    }
}
