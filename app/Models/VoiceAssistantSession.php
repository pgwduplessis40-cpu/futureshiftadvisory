<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VoiceAssistantSession extends Model
{
    use HasUuids;

    public const STATUS_STARTED = 'started';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const INTENT_CAPTURE_CALL_NOTE = 'capture_call_note';

    public const INTENT_CAPTURE_ACTIONS = 'capture_actions';

    protected $guarded = [];

    protected $casts = [
        'shortcut_payload' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public static function intents(): array
    {
        return [
            self::INTENT_CAPTURE_CALL_NOTE,
            self::INTENT_CAPTURE_ACTIONS,
        ];
    }

    /**
     * @return BelongsTo<Client, VoiceAssistantSession>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, VoiceAssistantSession>
     */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_user_id');
    }

    /**
     * @return BelongsTo<CallLog, VoiceAssistantSession>
     */
    public function callLog(): BelongsTo
    {
        return $this->belongsTo(CallLog::class);
    }
}
