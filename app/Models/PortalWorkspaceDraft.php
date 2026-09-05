<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PortalWorkspaceDraft extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'saved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, PortalWorkspaceDraft>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Client, PortalWorkspaceDraft>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
