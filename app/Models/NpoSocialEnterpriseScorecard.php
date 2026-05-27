<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NpoSocialEnterpriseScorecard extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'commercial_score' => 'integer',
        'mission_score' => 'integer',
        'commercial_weight' => 'integer',
        'mission_weight' => 'integer',
        'blended_score' => 'float',
        'commercial_axes' => 'array',
        'mission_axes' => 'array',
        'source_attributions' => 'array',
        'calculated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, NpoSocialEnterpriseScorecard>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<NpoEngagement, NpoSocialEnterpriseScorecard>
     */
    public function npoEngagement(): BelongsTo
    {
        return $this->belongsTo(NpoEngagement::class);
    }

    /**
     * @return HasMany<NpoTensionAnalysis>
     */
    public function tensionAnalyses(): HasMany
    {
        return $this->hasMany(NpoTensionAnalysis::class);
    }
}
