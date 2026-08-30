<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeeMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FeeCalculation extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'npo_engagement_id',
        'integration_scope_id',
        'method',
        'inputs',
        'suggested_low',
        'suggested_mid',
        'suggested_high',
        'improvement_pv_total',
        'risk_cost_pv_total',
        'roi_ratio',
        'justification',
    ];

    protected $casts = [
        'method' => FeeMethod::class,
        'inputs' => 'array',
        'suggested_low' => 'float',
        'suggested_mid' => 'float',
        'suggested_high' => 'float',
        'improvement_pv_total' => 'float',
        'risk_cost_pv_total' => 'float',
        'roi_ratio' => 'float',
        'justification' => 'array',
    ];

    /**
     * @return BelongsTo<Client, FeeCalculation>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<IntegrationScope, FeeCalculation>
     */
    public function integrationScope(): BelongsTo
    {
        return $this->belongsTo(IntegrationScope::class);
    }

    /**
     * @return BelongsTo<NpoEngagement, FeeCalculation>
     */
    public function npoEngagement(): BelongsTo
    {
        return $this->belongsTo(NpoEngagement::class);
    }

    /**
     * @return BelongsTo<User, FeeCalculation>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<Proposal>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }
}
