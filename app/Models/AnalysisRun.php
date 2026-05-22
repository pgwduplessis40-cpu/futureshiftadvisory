<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnalysisModule;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AnalysisRun extends Model
{
    use HasUuids;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_BLOCKED_DOCUMENTS = 'blocked_documents';

    public const STATUS_BLOCKED_DATA_QUALITY = 'blocked_data_quality';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'module' => AnalysisModule::class,
        'framework_lenses' => 'array',
        'data_quality_snapshot' => 'array',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, AnalysisRun>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, AnalysisRun>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<AnalysisFinding>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(AnalysisFinding::class);
    }

    /**
     * @return HasMany<Scenario>
     */
    public function scenarios(): HasMany
    {
        return $this->hasMany(Scenario::class);
    }

    /**
     * @return HasMany<SuccessionPlan>
     */
    public function successionPlans(): HasMany
    {
        return $this->hasMany(SuccessionPlan::class);
    }
}
