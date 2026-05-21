<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WellbeingCheckin extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date',
        'business_confidence' => 'integer',
        'personal_coping' => 'integer',
        'submitted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, WellbeingCheckin>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, WellbeingCheckin>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canBeDeletedBy(User $user): bool
    {
        return (string) $this->user_id === (string) $user->getKey()
            && $this->submitted_at !== null
            && $this->submitted_at->greaterThanOrEqualTo(now()->subDays(7));
    }
}
