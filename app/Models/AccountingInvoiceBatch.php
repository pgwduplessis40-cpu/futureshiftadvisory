<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AccountingInvoiceBatch extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CREATED = 'created';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'term_months' => 'integer',
        'monthly_amount' => 'decimal:2',
        'gst_rate_percent' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, AccountingInvoiceBatch>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Proposal, AccountingInvoiceBatch>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<PaymentSchedule, AccountingInvoiceBatch>
     */
    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    /**
     * @return BelongsTo<PracticeAccountingConnection, AccountingInvoiceBatch>
     */
    public function practiceAccountingConnection(): BelongsTo
    {
        return $this->belongsTo(PracticeAccountingConnection::class);
    }

    /**
     * @return HasMany<AccountingInvoice>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(AccountingInvoice::class);
    }
}
