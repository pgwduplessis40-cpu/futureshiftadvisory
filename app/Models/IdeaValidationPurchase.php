<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public, one-off Idea Validation purchase.
 *
 * It deliberately stores Stripe references and the purchased price snapshot,
 * never a card number, payment method, or other payment credential.
 */
final class IdeaValidationPurchase extends Model
{
    use HasUuids;

    public const STATUS_EMAIL_VERIFICATION_PENDING = 'email_verification_pending';

    public const STATUS_PAYMENT_PENDING = 'payment_pending';

    public const STATUS_PAYMENT_PROCESSING = 'payment_processing';

    public const STATUS_PAID = 'paid';

    public const STATUS_PAYMENT_FAILED = 'payment_failed';

    protected $guarded = [];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'amount_ex_gst' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'amount_including_gst' => 'decimal:2',
        'package_snapshot' => 'array',
        'payment_intent_created_at' => 'datetime',
        'paid_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    /** @return BelongsTo<TermsVersion, $this> */
    public function termsVersion(): BelongsTo
    {
        return $this->belongsTo(TermsVersion::class);
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

    /** @return BelongsTo<ServiceActivation, $this> */
    public function serviceActivation(): BelongsTo
    {
        return $this->belongsTo(ServiceActivation::class);
    }
}
