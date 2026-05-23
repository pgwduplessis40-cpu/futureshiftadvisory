<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DdWorkstream extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_FAILED = 'failed';

    public const PAUSED_ACCURACY_DISCREPANCY = 'accuracy_discrepancy';

    protected $guarded = [];

    protected $casts = [
        'data_room_item_ids' => 'array',
        'verification_weight' => 'integer',
        'nz_checks' => 'array',
        'ran_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, DdWorkstream>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<DdEngagement, DdWorkstream>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(DdEngagement::class, 'dd_engagement_id');
    }

    /**
     * @return BelongsTo<AnalysisRun, DdWorkstream>
     */
    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    /**
     * @return BelongsTo<User, DdWorkstream>
     */
    public function ranBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ran_by_user_id');
    }
}
