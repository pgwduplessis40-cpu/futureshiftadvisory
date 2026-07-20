<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScreenShareSession extends Model
{
    use HasUuids;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED_PENDING_BROWSER = 'approved_pending_browser';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_at' => 'immutable_datetime',
            'client_response_at' => 'immutable_datetime',
            'picker_deadline_at' => 'immutable_datetime',
            'session_started_at' => 'immutable_datetime',
            'session_ended_at' => 'immutable_datetime',
            'last_heartbeat_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'browser_permission_granted' => 'boolean',
            'consent_context' => 'array',
            'authorization_basis' => 'array',
            'prompted_connections' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Client, ScreenShareSession>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<EntrepreneurProfile, ScreenShareSession>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<User, ScreenShareSession>
     */
    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    /**
     * @return BelongsTo<User, ScreenShareSession>
     */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    /**
     * @return BelongsTo<ScreenShareConnection, ScreenShareSession>
     */
    public function clientConnection(): BelongsTo
    {
        return $this->belongsTo(ScreenShareConnection::class, 'client_connection_id');
    }

    /**
     * @return BelongsTo<ScreenShareConnection, ScreenShareSession>
     */
    public function advisorConnection(): BelongsTo
    {
        return $this->belongsTo(ScreenShareConnection::class, 'advisor_connection_id');
    }

    public function isTerminal(): bool
    {
        return $this->status === self::STATUS_ENDED;
    }
}
