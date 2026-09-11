<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentRefund extends Model
{
    use HasUuids;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_FAILED = 'failed';

    /**
     * The cancellation service creates a refund request with these values.
     *
     * Gateway results and refund state changes are service-owned transitions
     * and are applied with forceFill(), rather than accepting request input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'service_activation_id',
        'requested_by_user_id',
        'gateway',
        'payment_reference',
        'amount',
        'currency',
        'status',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<ServiceActivation, $this> */
    public function serviceActivation(): BelongsTo
    {
        return $this->belongsTo(ServiceActivation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
