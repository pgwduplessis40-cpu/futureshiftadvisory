<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentWebhookEvent extends Model
{
    use HasUuids;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Payment, PaymentWebhookEvent>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Client, PaymentWebhookEvent>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
