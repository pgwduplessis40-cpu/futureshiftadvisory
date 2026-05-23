<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CallLog extends Model
{
    use HasUuids;

    public const CHANNEL_VOICE_NOTE = 'voice_note';

    public const CHANNEL_PHONE_CALL = 'phone_call';

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
        'action_items' => 'array',
    ];

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<VoiceNote, $this>
     */
    public function voiceNote(): BelongsTo
    {
        return $this->belongsTo(VoiceNote::class);
    }

    /**
     * @return HasMany<MilestoneAction, $this>
     */
    public function milestoneActions(): HasMany
    {
        return $this->hasMany(MilestoneAction::class);
    }
}
