<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BulkCommunicationRecipient extends Model
{
    use HasUuids;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_IN_PLATFORM = 'in_platform';

    public const CHANNEL_EMAIL_AND_IN_PLATFORM = 'email_and_in_platform';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'delivery_metadata' => 'array',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<BulkCommunication, BulkCommunicationRecipient>
     */
    public function bulkCommunication(): BelongsTo
    {
        return $this->belongsTo(BulkCommunication::class);
    }

    /**
     * @return BelongsTo<Client, BulkCommunicationRecipient>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, BulkCommunicationRecipient>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Message, BulkCommunicationRecipient>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
