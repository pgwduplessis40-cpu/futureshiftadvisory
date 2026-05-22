<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SuccessionPlan extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'exit_readiness_score' => 'integer',
        'options' => 'array',
        'owner_dependency_plan' => 'array',
        'target_exit_pv' => 'float',
        'owner_readiness_is_primary_constraint' => 'boolean',
    ];

    /**
     * @return BelongsTo<Client, SuccessionPlan>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<AnalysisRun, SuccessionPlan>
     */
    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    /**
     * @return BelongsTo<PvCalculation, SuccessionPlan>
     */
    public function targetExitPvCalculation(): BelongsTo
    {
        return $this->belongsTo(PvCalculation::class, 'target_exit_pv_calculation_id');
    }

    /**
     * @return BelongsTo<User, SuccessionPlan>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
