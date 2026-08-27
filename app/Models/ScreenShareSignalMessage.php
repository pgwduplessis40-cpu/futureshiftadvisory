<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScreenShareSignalMessage extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'session_id',
        'recipient_connection_id',
        'type',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<ScreenShareSession, ScreenShareSignalMessage>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ScreenShareSession::class);
    }

    /**
     * @return BelongsTo<ScreenShareConnection, ScreenShareSignalMessage>
     */
    public function recipientConnection(): BelongsTo
    {
        return $this->belongsTo(ScreenShareConnection::class, 'recipient_connection_id');
    }
}
