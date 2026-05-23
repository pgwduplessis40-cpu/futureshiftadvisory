<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReadinessAssessment extends Model
{
    use HasUuids;

    public const OUTCOME_READY = 'ready';

    public const OUTCOME_DEVELOP_FIRST = 'develop_first';

    public const OUTCOME_NOT_YET = 'not_yet';

    protected $guarded = [];

    protected $casts = [
        'responses' => 'array',
        'score' => 'float',
        'personal_barriers' => 'array',
        'assessed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EntrepreneurProfile, ReadinessAssessment>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<User, ReadinessAssessment>
     */
    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id');
    }
}
