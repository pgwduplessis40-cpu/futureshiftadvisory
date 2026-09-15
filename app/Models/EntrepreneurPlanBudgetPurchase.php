<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-off Business Plan & Budget add-on purchased by an Idea Validation client.
 *
 * Payment confirms entitlement. Workspace activation is deliberately separate:
 * it happens only after an advisor has approved the Idea Validation that seeds
 * the plan.
 */
final class EntrepreneurPlanBudgetPurchase extends Model
{
    use HasUuids;

    public const STATUS_PAYMENT_PENDING = 'payment_pending';

    public const STATUS_PAYMENT_PROCESSING = 'payment_processing';

    public const STATUS_PAID = 'paid';

    public const STATUS_PAYMENT_FAILED = 'payment_failed';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'client_id',
        'entrepreneur_profile_id',
        'advisor_id',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount_ex_gst' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'amount_including_gst' => 'decimal:2',
        'package_snapshot' => 'array',
        'payment_intent_created_at' => 'datetime',
        'paid_at' => 'datetime',
        'activated_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<EntrepreneurProfile, $this> */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    /** @return BelongsTo<ServiceRatePackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ServiceRatePackage::class, 'service_rate_package_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<IdeaValidation, $this> */
    public function approvedIdeaValidation(): BelongsTo
    {
        return $this->belongsTo(IdeaValidation::class, 'approved_idea_validation_id');
    }

    public function activated(): bool
    {
        return $this->status === self::STATUS_PAID && $this->activated_at !== null;
    }
}
