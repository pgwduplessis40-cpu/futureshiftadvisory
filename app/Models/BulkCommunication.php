<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BulkCommunication extends Model
{
    use HasUuids;

    public const AUDIENCE_ALL_CLIENTS = 'all_clients';

    public const AUDIENCE_SELECTED_CLIENTS = 'selected_clients';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENT = 'sent';

    public const TEMPLATE_GENERAL_UPDATE = 'general_update';

    public const TEMPLATE_ACTION_REQUIRED = 'action_required';

    public const TEMPLATE_EVENT_INVITE = 'event_invite';

    protected $guarded = [];

    protected $casts = [
        'selected_client_ids' => 'array',
        'metrics' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, BulkCommunication>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<BulkCommunicationRecipient>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(BulkCommunicationRecipient::class);
    }

    /**
     * @return array<int, string>
     */
    public static function audienceTypes(): array
    {
        return [
            self::AUDIENCE_ALL_CLIENTS,
            self::AUDIENCE_SELECTED_CLIENTS,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function templates(): array
    {
        return [
            self::TEMPLATE_GENERAL_UPDATE => 'General update',
            self::TEMPLATE_ACTION_REQUIRED => 'Action required',
            self::TEMPLATE_EVENT_INVITE => 'Event invite',
        ];
    }
}
