<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 */
final class FoundingRoadmapVersion extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
        'planning_start_date' => 'date',
        'planning_through_date' => 'date',
        'agenda' => 'array',
        'replan_input' => 'array',
        'change_summary' => 'array',
        'generated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<FoundingAdvisoryEngagement, FoundingRoadmapVersion>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(FoundingAdvisoryEngagement::class, 'founding_advisory_engagement_id');
    }

    /**
     * @return BelongsTo<Client, FoundingRoadmapVersion>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<StrategicPlan, FoundingRoadmapVersion>
     */
    public function strategicPlan(): BelongsTo
    {
        return $this->belongsTo(StrategicPlan::class);
    }

    /**
     * @return BelongsTo<User, FoundingRoadmapVersion>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /**
     * @return BelongsTo<User, FoundingRoadmapVersion>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }
}
