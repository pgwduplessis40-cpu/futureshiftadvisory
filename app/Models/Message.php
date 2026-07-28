<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Message extends Model
{
    /**
     * @var array<int, string>
     */
    public const ADVISOR_PENDING_SENDER_TYPES = [
        User::TYPE_CLIENT_PRIMARY,
        User::TYPE_CLIENT_TEAM,
        User::TYPE_ENTREPRENEUR,
    ];

    use HasUuids;

    public const CHANNEL_IN_APP = 'in_app';

    public const CHANNEL_EMAIL = 'email';

    public const DELIVERY_SENT = 'sent';

    public const DELIVERY_PARTIAL = 'partial';

    public const DELIVERY_FAILED = 'failed';

    public const DELIVERY_SKIPPED_PREFERENCE = 'skipped_preference';

    public const DELIVERY_SKIPPED_PARALLEL_IN_APP = 'skipped_parallel_in_app';

    protected $guarded = [];

    protected $casts = [
        'attachments' => 'array',
        'channel_decision' => 'array',
        'email_recipients' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<MessageThread, Message>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }

    /**
     * @return BelongsTo<User, Message>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function needsAdvisorAttention(): bool
    {
        return $this->sender instanceof User
            && in_array($this->sender->user_type, self::ADVISOR_PENDING_SENDER_TYPES, true);
    }
}
