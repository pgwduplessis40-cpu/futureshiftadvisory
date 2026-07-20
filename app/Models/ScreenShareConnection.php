<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScreenShareConnection extends Model
{
    use HasUuids;

    public const TYPE_ADVISOR = 'advisor';

    public const TYPE_CLIENT = 'client';

    protected $guarded = [];

    protected $hidden = [
        'secret_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, ScreenShareConnection>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<EntrepreneurProfile, ScreenShareConnection>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<User, ScreenShareConnection>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
