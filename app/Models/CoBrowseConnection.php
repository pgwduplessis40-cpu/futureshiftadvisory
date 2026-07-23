<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CoBrowseConnection extends Model
{
    use HasUuids;

    public const TYPE_ADVISOR = 'advisor';

    public const TYPE_CLIENT = 'client';

    protected $guarded = [];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Client, CoBrowseConnection> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<EntrepreneurProfile, CoBrowseConnection> */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /** @return BelongsTo<User, CoBrowseConnection> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
