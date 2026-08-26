<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class CoBrowseSession extends Model
{
    use HasUuids;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'entrepreneur_profile_id',
        'client_user_id',
        'advisor_id',
        'client_connection_id',
        'advisor_connection_id',
        'status',
        'client_response',
        'client_response_at',
        'requested_at',
        'session_started_at',
        'session_ended_at',
        'end_reason',
        'last_heartbeat_at',
        'expires_at',
        'consent_context',
        'authorization_basis',
        'prompted_connections',
        'actions_count',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'immutable_datetime',
            'client_response_at' => 'immutable_datetime',
            'session_started_at' => 'immutable_datetime',
            'session_ended_at' => 'immutable_datetime',
            'last_heartbeat_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'consent_context' => 'array',
            'authorization_basis' => 'array',
            'prompted_connections' => 'array',
        ];
    }
}
