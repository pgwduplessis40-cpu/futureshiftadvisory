<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentAccountingSync extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_FAILED = 'failed';

    public const REFUND_NOT_REQUESTED = 'not_requested';

    public const REFUND_PENDING = 'pending';

    public const REFUND_SYNCED = 'synced';

    public const REFUND_FAILED = 'failed';

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'payment_id',
        'provider',
        'source',
        'amount_ex_gst',
        'gst_amount',
        'amount_including_gst',
        'currency',
        'status',
        'refund_status',
        'metadata',
    ];

    protected $casts = [
        'amount_ex_gst' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'amount_including_gst' => 'decimal:2',
        'synced_at' => 'datetime',
        'refunded_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<PaymentRefund, $this> */
    public function paymentRefund(): BelongsTo
    {
        return $this->belongsTo(PaymentRefund::class);
    }

    /** @return BelongsTo<PracticeAccountingConnection, $this> */
    public function practiceAccountingConnection(): BelongsTo
    {
        return $this->belongsTo(PracticeAccountingConnection::class);
    }
}
