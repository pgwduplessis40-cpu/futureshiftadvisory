<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MessageThreadParticipant extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'last_read_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<MessageThread, MessageThreadParticipant>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }

    /**
     * @return BelongsTo<User, MessageThreadParticipant>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
