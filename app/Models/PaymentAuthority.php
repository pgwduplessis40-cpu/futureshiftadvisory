<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentAuthority extends Model
{
    use HasUuids;

    public const TYPE_CARD = 'card';

    public const TYPE_DIRECT_DEBIT = 'direct_debit';

    public const GATEWAY_STRIPE = 'stripe';

    public const GATEWAY_WINDCAVE = 'windcave';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVOKED = 'revoked';

    protected $guarded = [];

    protected $casts = [
        'authorised_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [self::TYPE_CARD, self::TYPE_DIRECT_DEBIT];
    }

    /**
     * @return array<int, string>
     */
    public static function gateways(): array
    {
        return [self::GATEWAY_STRIPE, self::GATEWAY_WINDCAVE];
    }

    /**
     * @return BelongsTo<Client, PaymentAuthority>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Proposal, PaymentAuthority>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<User, PaymentAuthority>
     */
    public function authorisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorised_by_user_id');
    }
}
