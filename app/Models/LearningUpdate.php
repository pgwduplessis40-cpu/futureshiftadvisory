<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LearningUpdate extends Model
{
    use HasUuids;

    public const STATUS_DETECTED = 'detected';

    public const STATUS_STAGED = 'staged';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_DEFERRED = 'deferred';

    public const STATUS_IMPLEMENTED = 'implemented';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $guarded = [];

    protected $casts = [
        'source' => 'array',
        'proposed_change' => 'array',
        'impact_scope' => 'array',
        'evidence' => 'array',
        'confidence' => 'float',
        'clients_affected' => 'integer',
        'effective_date' => 'datetime',
        'pre_implementation_notice_at' => 'datetime',
        'review_due_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    /**
     * @return HasMany<LearningUpdateDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(LearningUpdateDecision::class);
    }

    /**
     * @return HasMany<LearningUpdateImplementation, $this>
     */
    public function implementations(): HasMany
    {
        return $this->hasMany(LearningUpdateImplementation::class);
    }
}
