<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Payment extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RETRYING = 'retrying';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'attempt' => 'integer',
        'processed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, Payment>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<PaymentSchedule, Payment>
     */
    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    /**
     * @return BelongsTo<PaymentAuthority, Payment>
     */
    public function paymentAuthority(): BelongsTo
    {
        return $this->belongsTo(PaymentAuthority::class);
    }

    /**
     * @return BelongsTo<PaymentInstallment, Payment>
     */
    public function paymentInstallment(): BelongsTo
    {
        return $this->belongsTo(PaymentInstallment::class);
    }

    /**
     * @return HasOne<Receipt>
     */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}
