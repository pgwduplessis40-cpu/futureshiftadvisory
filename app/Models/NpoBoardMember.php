<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NpoBoardMember extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'npo_engagement_id',
        'user_id',
        'treasurer',
        'active',
        'revoked_at',
        'created_by_user_id',
        'revoked_by_user_id',
    ];

    protected $casts = [
        'treasurer' => 'boolean',
        'active' => 'boolean',
        'revoked_at' => 'datetime',
    ];

    public function active(): bool
    {
        return $this->active === true && $this->revoked_at === null;
    }

    /**
     * @return BelongsTo<Client, NpoBoardMember>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<NpoEngagement, NpoBoardMember>
     */
    public function npoEngagement(): BelongsTo
    {
        return $this->belongsTo(NpoEngagement::class);
    }

    /**
     * @return BelongsTo<User, NpoBoardMember>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
