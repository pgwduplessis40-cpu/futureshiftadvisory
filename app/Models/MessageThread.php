<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MessageThread extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, MessageThread>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<EntrepreneurProfile, MessageThread>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<User, MessageThread>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<MessageThreadParticipant>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(MessageThreadParticipant::class, 'thread_id');
    }

    /**
     * @return HasMany<Message>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }
}
