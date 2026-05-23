<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DdIntegrationPlanItem extends Model
{
    use HasUuids;

    protected $table = 'dd_integration_plans';

    protected $guarded = [];

    protected $casts = [
        'day' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Client, DdIntegrationPlanItem>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<DdEngagement, DdIntegrationPlanItem>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(DdEngagement::class, 'dd_engagement_id');
    }

    /**
     * @return BelongsTo<DdRiskRegisterItem, DdIntegrationPlanItem>
     */
    public function riskRegisterItem(): BelongsTo
    {
        return $this->belongsTo(DdRiskRegisterItem::class, 'dd_risk_register_id');
    }
}
