<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AccountingInvoice extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_AUTHORISED = 'authorised';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'sequence' => 'integer',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'amount_ex_gst' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'amount_inc_gst' => 'decimal:2',
        'payload' => 'array',
        'response' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AccountingInvoiceBatch, AccountingInvoice>
     */
    public function accountingInvoiceBatch(): BelongsTo
    {
        return $this->belongsTo(AccountingInvoiceBatch::class);
    }

    /**
     * @return BelongsTo<Client, AccountingInvoice>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Proposal, AccountingInvoice>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
