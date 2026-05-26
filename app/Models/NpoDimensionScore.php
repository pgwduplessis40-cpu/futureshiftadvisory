<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NpoTiritiMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NpoDimensionScore extends Model
{
    use HasUuids;

    public const SOURCE_ADVISOR_ASSESSMENT = 'advisor_assessment';

    public const SOURCE_GOVERNANCE_REVIEW_PREPOPULATION = 'governance_review_prepopulation';

    protected $guarded = [];

    protected $casts = [
        'dimension_number' => 'integer',
        'tiriti_mode' => NpoTiritiMode::class,
        'score' => 'integer',
        'advisor_weight' => 'integer',
        'weighted_score' => 'float',
        'health_score' => 'integer',
        'findings' => 'array',
        'mode_b_criteria_contributions' => 'array',
        'source_attributions' => 'array',
        'scoring_context' => 'array',
        'captured_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, NpoDimensionScore>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<NpoEngagement, NpoDimensionScore>
     */
    public function npoEngagement(): BelongsTo
    {
        return $this->belongsTo(NpoEngagement::class);
    }

    /**
     * @return BelongsTo<NpoEngagement, NpoDimensionScore>
     */
    public function sourceNpoEngagement(): BelongsTo
    {
        return $this->belongsTo(NpoEngagement::class, 'source_npo_engagement_id');
    }
}
