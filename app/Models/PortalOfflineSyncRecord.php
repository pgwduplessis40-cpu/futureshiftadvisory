<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PortalOfflineSyncRecord extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'response_payload' => 'array',
        'status_code' => 'integer',
    ];

    /**
     * @return BelongsTo<User, PortalOfflineSyncRecord>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Client, PortalOfflineSyncRecord>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
