<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MailOAuthConnection extends Model
{
    use HasUuids;

    protected $table = 'mail_oauth_connections';

    public const PROVIDER_MICROSOFT_GRAPH = 'microsoft_graph';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_ERROR = 'error';

    protected $guarded = [];

    protected $hidden = [
        'access_token_envelope',
        'refresh_token_envelope',
    ];

    protected $casts = [
        'access_token_envelope_meta' => 'array',
        'refresh_token_envelope_meta' => 'array',
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function connected(): bool
    {
        return $this->status === self::STATUS_CONNECTED && $this->revoked_at === null;
    }

    /**
     * @return BelongsTo<User, MailOAuthConnection>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    /**
     * @return BelongsTo<User, MailOAuthConnection>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }
}
