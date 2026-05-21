<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OffboardingRecord extends Model
{
    use HasUuids;

    public const STATUS_COMPLETED = 'completed';

    protected $guarded = [];

    protected $casts = [
        'triggered_at' => 'datetime',
        'reengagement_due' => 'datetime',
        'reengagement_reminder_sent_at' => 'datetime',
        'advisor_capacity_released' => 'boolean',
        'advisor_capacity_before' => 'integer',
        'advisor_capacity_after' => 'integer',
        'advisor_capacity_delta' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Client, OffboardingRecord>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, OffboardingRecord>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
