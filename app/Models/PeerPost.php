<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class PeerPost extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'posted_at' => 'datetime',
        'visible_at' => 'datetime',
        'reported_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PeerNetworkMember, PeerPost>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(PeerNetworkMember::class, 'peer_network_member_id');
    }

    /**
     * @return HasOne<PeerPostModeration>
     */
    public function moderation(): HasOne
    {
        return $this->hasOne(PeerPostModeration::class);
    }
}
