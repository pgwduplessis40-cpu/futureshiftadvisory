<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ServiceJourneyPointEvent extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'service_journey_enrollment_id',
        'service_journey_milestone_award_id',
        'milestone_key',
        'points',
        'earned_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'earned_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ServiceJourneyEnrollment, ServiceJourneyPointEvent>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(ServiceJourneyEnrollment::class, 'service_journey_enrollment_id');
    }

    /**
     * @return BelongsTo<ServiceJourneyMilestoneAward, ServiceJourneyPointEvent>
     */
    public function milestoneAward(): BelongsTo
    {
        return $this->belongsTo(ServiceJourneyMilestoneAward::class, 'service_journey_milestone_award_id');
    }
}
