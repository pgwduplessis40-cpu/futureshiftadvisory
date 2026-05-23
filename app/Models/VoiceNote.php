<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class VoiceNote extends Model
{
    use HasUuids;

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_TRANSCRIBED = 'transcribed';

    public const STATUS_SUMMARIZED = 'summarized';

    protected $guarded = [];

    protected $casts = [
        'duration_seconds' => 'integer',
        'transcription_metadata' => 'array',
        'summary_payload' => 'array',
        'transcribed_at' => 'datetime',
        'summarized_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return HasOne<CallLog, $this>
     */
    public function callLog(): HasOne
    {
        return $this->hasOne(CallLog::class);
    }
}
