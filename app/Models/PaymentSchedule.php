<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentSchedule extends Model
{
    use HasUuids;

    public const CADENCE_ONE_OFF = 'one_off';

    public const CADENCE_MONTHLY_RETAINER = 'monthly_retainer';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_COMPLETED = 'completed';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'collection_day' => 'integer',
        'next_run_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public static function cadences(): array
    {
        return [self::CADENCE_ONE_OFF, self::CADENCE_MONTHLY_RETAINER];
    }

    /**
     * @return BelongsTo<Client, PaymentSchedule>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Proposal, PaymentSchedule>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<PaymentAuthority, PaymentSchedule>
     */
    public function paymentAuthority(): BelongsTo
    {
        return $this->belongsTo(PaymentAuthority::class);
    }

    /**
     * @return BelongsTo<User, PaymentSchedule>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<Payment>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
