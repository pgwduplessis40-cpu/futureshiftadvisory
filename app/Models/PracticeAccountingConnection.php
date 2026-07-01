<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PracticeAccountingConnection extends Model
{
    use HasUuids;

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_REVOKED = 'revoked';

    protected $guarded = [];

    protected $casts = [
        'token_envelope_meta' => 'array',
        'scopes' => 'array',
        'connected_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_invoice_sync_at' => 'datetime',
    ];

    public function connected(): bool
    {
        return $this->status === self::STATUS_CONNECTED && $this->revoked_at === null;
    }

    /**
     * @return BelongsTo<User, PracticeAccountingConnection>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    /**
     * @return BelongsTo<User, PracticeAccountingConnection>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /**
     * @return HasMany<AccountingInvoiceBatch>
     */
    public function accountingInvoiceBatches(): HasMany
    {
        return $this->hasMany(AccountingInvoiceBatch::class);
    }
}
