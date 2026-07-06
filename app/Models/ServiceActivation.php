<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ServiceActivation extends Model
{
    use HasUuids;

    public const SERVICE_DUE_DILIGENCE = 'due_diligence';

    public const SERVICE_ENTREPRENEUR = 'entrepreneur';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_PACKAGE_SELECTED = 'package_selected';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REJECTED = 'rejected';

    public const PAYMENT_NOT_REQUIRED = 'not_required';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_DEPOSIT_PENDING = 'deposit_pending';

    public const PAYMENT_BALANCE_PENDING = 'balance_pending';

    public const PAYMENT_PAID = 'paid';

    protected $guarded = [];

    protected $casts = [
        'intake' => 'array',
        'selected_package_snapshot' => 'array',
        'payment_completed_at' => 'datetime',
        'deposit_paid_at' => 'datetime',
        'balance_received_at' => 'datetime',
        'accepted_at' => 'datetime',
        'terms_reference' => 'array',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Client, ServiceActivation>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, ServiceActivation>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, ServiceActivation>
     */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    /**
     * @return BelongsTo<User, ServiceActivation>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * @return BelongsTo<User, ServiceActivation>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    /**
     * @return BelongsTo<ServiceRatePackage, ServiceActivation>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ServiceRatePackage::class, 'service_rate_package_id');
    }

    /**
     * @return BelongsTo<DdEngagement, ServiceActivation>
     */
    public function ddEngagement(): BelongsTo
    {
        return $this->belongsTo(DdEngagement::class, 'related_dd_engagement_id');
    }

    /**
     * @return BelongsTo<EntrepreneurProfile, ServiceActivation>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class, 'related_entrepreneur_profile_id');
    }

    /**
     * @return BelongsTo<MessageThread, ServiceActivation>
     */
    public function messageThread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'client_message_thread_id');
    }

    public function clientLabel(): string
    {
        return match ($this->service_type) {
            self::SERVICE_DUE_DILIGENCE => 'Explore buying a business',
            self::SERVICE_ENTREPRENEUR => 'Test new Business Idea',
            default => $this->client_label,
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [
            self::STATUS_CANCELLED,
            self::STATUS_CLOSED,
            self::STATUS_REJECTED,
        ], true);
    }

    public function paymentRequired(): bool
    {
        return in_array($this->payment_status, [
            self::PAYMENT_PENDING,
            self::PAYMENT_DEPOSIT_PENDING,
            self::PAYMENT_BALANCE_PENDING,
        ], true)
            || (
                is_array($this->selected_package_snapshot)
                && (float) ($this->selected_package_snapshot['fixed_fee'] ?? 0) > 0
                && $this->payment_status !== self::PAYMENT_PAID
            );
    }

    public function paymentComplete(): bool
    {
        return in_array($this->payment_status, [
            self::PAYMENT_NOT_REQUIRED,
            self::PAYMENT_PAID,
        ], true);
    }

    public function depositPaid(): bool
    {
        return $this->deposit_paid_at !== null || $this->paymentComplete();
    }

    public function balancePending(): bool
    {
        return $this->payment_status === self::PAYMENT_BALANCE_PENDING;
    }
}
