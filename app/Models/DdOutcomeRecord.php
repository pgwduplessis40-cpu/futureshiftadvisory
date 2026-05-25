<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DdOutcomeRecord extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'recorded_price' => 'float',
        'actual_outcome' => 'array',
        'recorded_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, DdOutcomeRecord>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<DdEngagement, DdOutcomeRecord>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(DdEngagement::class, 'dd_engagement_id');
    }
}
