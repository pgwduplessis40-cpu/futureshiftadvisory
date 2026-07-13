<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BillingAdjustmentApplication extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'amount_applied' => 'float',
    ];

    /** @return BelongsTo<Client, BillingAdjustmentApplication> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<BillingAdjustment, BillingAdjustmentApplication> */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(BillingAdjustment::class, 'adjustment_id');
    }

    /** @return BelongsTo<PaymentInstallment, BillingAdjustmentApplication> */
    public function paymentInstallment(): BelongsTo
    {
        return $this->belongsTo(PaymentInstallment::class);
    }
}
