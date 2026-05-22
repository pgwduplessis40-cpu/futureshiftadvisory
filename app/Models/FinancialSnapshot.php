<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinancialSnapshot extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'degraded' => 'boolean',
        'profit_and_loss' => 'array',
        'balance_sheet' => 'array',
        'cash_flow' => 'array',
        'metrics' => 'array',
        'pulled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, FinancialSnapshot>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<AccountingConnection, FinancialSnapshot>
     */
    public function accountingConnection(): BelongsTo
    {
        return $this->belongsTo(AccountingConnection::class);
    }
}
