<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocumentExpiryReminder extends Model
{
    use HasUuids;

    public const TYPE_EXPIRES_SOON = 'expires_soon';

    protected $guarded = [];

    protected $casts = [
        'expires_at_snapshot' => 'datetime',
        'triggered_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Document, DocumentExpiryReminder>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<Client, DocumentExpiryReminder>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, DocumentExpiryReminder>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
