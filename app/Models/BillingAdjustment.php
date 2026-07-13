<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BillingAdjustment extends Model
{
    use HasUuids;

    public const TYPE_SCOPING_FEE_CREDIT = 'scoping_fee_credit';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_VOID = 'void';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'applied_at' => 'datetime',
    ];

    /** @return BelongsTo<Client, BillingAdjustment> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<ServiceActivation, BillingAdjustment> */
    public function sourceServiceActivation(): BelongsTo
    {
        return $this->belongsTo(ServiceActivation::class, 'source_service_activation_id');
    }

    /** @return BelongsTo<Proposal, BillingAdjustment> */
    public function appliedToProposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'applied_to_proposal_id');
    }

    /** @return HasMany<BillingAdjustmentApplication> */
    public function applications(): HasMany
    {
        return $this->hasMany(BillingAdjustmentApplication::class, 'adjustment_id');
    }
}
