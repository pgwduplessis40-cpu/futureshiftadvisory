<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class CoBrowseAction extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'id',
        'session_id',
        'recipient_connection_id',
        'sender_connection_id',
        'type',
        'payload',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
