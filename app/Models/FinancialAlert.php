<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinancialAlert extends Model
{
    use HasUuids;

    public const CATEGORY_PROFITABILITY = 'profitability';

    public const CATEGORY_CASH_FLOW = 'cash_flow';

    public const CATEGORY_LIQUIDITY = 'liquidity';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'client_id',
        'accounting_connection_id',
        'previous_snapshot_id',
        'current_snapshot_id',
        'alert_key',
        'category',
        'severity',
        'metric',
        'headline',
        'detail',
        'previous_value',
        'current_value',
        'change_amount',
        'change_percent',
        'citation',
        'surfaced_at',
        'notified_at',
    ];

    protected $casts = [
        'previous_value' => 'float',
        'current_value' => 'float',
        'change_amount' => 'float',
        'change_percent' => 'float',
        'citation' => 'array',
        'surfaced_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, FinancialAlert>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<AccountingConnection, FinancialAlert>
     */
    public function accountingConnection(): BelongsTo
    {
        return $this->belongsTo(AccountingConnection::class);
    }

    /**
     * @return BelongsTo<FinancialSnapshot, FinancialAlert>
     */
    public function previousSnapshot(): BelongsTo
    {
        return $this->belongsTo(FinancialSnapshot::class, 'previous_snapshot_id');
    }

    /**
     * @return BelongsTo<FinancialSnapshot, FinancialAlert>
     */
    public function currentSnapshot(): BelongsTo
    {
        return $this->belongsTo(FinancialSnapshot::class, 'current_snapshot_id');
    }
}
