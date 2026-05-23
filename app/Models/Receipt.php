<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Receipt extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'receipt_envelope_meta' => 'array',
        'receipt_byte_size' => 'integer',
        'generated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, Receipt>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Payment, Receipt>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
