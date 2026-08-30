<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class ServiceJourneyMilestoneAward extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'service_journey_enrollment_id',
        'milestone_key',
        'evidence_source_type',
        'evidence_source_id',
        'evidence_snapshot',
        'earned_at',
        'seen_at',
    ];

    protected $casts = [
        'evidence_snapshot' => 'array',
        'earned_at' => 'datetime',
        'seen_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ServiceJourneyEnrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(ServiceJourneyEnrollment::class, 'service_journey_enrollment_id');
    }

    /**
     * @return HasOne<ServiceJourneyPointEvent, $this>
     */
    public function pointEvent(): HasOne
    {
        return $this->hasOne(ServiceJourneyPointEvent::class);
    }
}
