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

    protected $guarded = [];

    protected $casts = [
        'fixed_fee' => 'float',
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
            'package_name' => $this->package_name,
            'client_label' => $this->client_label,
            'billing_model' => $this->billing_model,
            'fixed_fee' => $this->fixed_fee,
            'hourly_rate' => $this->hourly_rate,
            'retainer_amount' => $this->retainer_amount,
            'purchase_price_min' => $this->purchase_price_min,
            'purchase_price_max' => $this->purchase_price_max,
            'currency' => $this->currency,
            'scope_description' => $this->scope_description,
            'effective_from' => $this->effective_from?->toIso8601String(),
        ];
    }
}
