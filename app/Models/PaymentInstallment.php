<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentInstallment extends Model
{
    use HasUuids;

    public const STATUS_DUE = 'due';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_AWAITING_GATEWAY_CONFIRMATION = 'awaiting_gateway_confirmation';

    public const STATUS_MANUAL_REVIEW = 'manual_review';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_SETTLED_ZERO = 'settled_zero';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'base_amount' => 'float',
        'credit_applied' => 'float',
        'net_amount' => 'float',
        'next_attempt_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'next_confirmation_at' => 'datetime',
        'confirmation_deadline' => 'datetime',
    ];

    /** @return BelongsTo<Client, PaymentInstallment> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<PaymentSchedule, PaymentInstallment> */
    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    /** @return BelongsTo<Payment, PaymentInstallment> */
    public function activePayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'active_payment_id');
    }

    /** @return HasMany<Payment> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<BillingAdjustmentApplication> */
    public function adjustmentApplications(): HasMany
    {
        return $this->hasMany(BillingAdjustmentApplication::class);
    }
}
