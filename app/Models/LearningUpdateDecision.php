<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LearningUpdateDecision extends Model
{
    use HasUuids;

    public const DECISION_APPROVE = 'approve';

    public const DECISION_APPROVE_MODIFIED_DATE = 'approve_modified_date';

    public const DECISION_DEFER = 'defer';

    public const DECISION_REJECT = 'reject';

    protected $guarded = [];

    protected $casts = [
        'effective_date' => 'datetime',
        'decided_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<LearningUpdate, $this>
     */
    public function learningUpdate(): BelongsTo
    {
        return $this->belongsTo(LearningUpdate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
