<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DdRiskRegisterItem extends Model
{
    use HasUuids;

    public const LEVEL_DEAL_KILLER = 'deal_killer';

    public const LEVEL_MAJOR = 'major';

    public const LEVEL_MINOR = 'minor';

    public const LEVEL_INFORMATIONAL = 'informational';

    protected $table = 'dd_risk_register';

    protected $guarded = [];

    protected $casts = [
        'financial_impact' => 'float',
        'probability' => 'float',
        'pv_of_cost' => 'float',
        'price_adjustment_nzd' => 'float',
        'rank' => 'integer',
        'source_attributions' => 'array',
    ];

    /**
     * @return BelongsTo<Client, DdRiskRegisterItem>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<DdEngagement, DdRiskRegisterItem>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(DdEngagement::class, 'dd_engagement_id');
    }

    /**
     * @return BelongsTo<AnalysisFinding, DdRiskRegisterItem>
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(AnalysisFinding::class, 'analysis_finding_id');
    }

    /**
     * @return BelongsTo<RiskCost, DdRiskRegisterItem>
     */
    public function riskCost(): BelongsTo
    {
        return $this->belongsTo(RiskCost::class);
    }
}
