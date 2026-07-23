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

    protected $guarded = [];

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
