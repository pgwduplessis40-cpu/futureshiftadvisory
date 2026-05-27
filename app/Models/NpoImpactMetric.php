<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NpoImpactMetric extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'value' => 'float',
        'platform_value' => 'float',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    /**
     * @return BelongsTo<Client, NpoImpactMetric>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<NpoEngagement, NpoImpactMetric>
     */
    public function npoEngagement(): BelongsTo
    {
        return $this->belongsTo(NpoEngagement::class);
    }

    /**
     * @return BelongsTo<User, NpoImpactMetric>
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }
}
