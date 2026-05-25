<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PeerPostModeration extends Model
{
    use HasUuids;

    protected $table = 'peer_post_moderation';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $guarded = [];

    protected $casts = [
        'moderated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PeerPost, PeerPostModeration>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(PeerPost::class, 'peer_post_id');
    }

    /**
     * @return BelongsTo<User, PeerPostModeration>
     */
    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_user_id');
    }
}
