<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ServiceJourneyEnrollment extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'participant_user_id',
        'service_key',
        'program_version',
        'recognition_enabled',
        'timezone',
        'recognition_enabled_at',
        'recognition_disabled_at',
    ];

    protected $casts = [
        'recognition_enabled' => 'boolean',
        'recognition_enabled_at' => 'datetime',
        'recognition_disabled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, ServiceJourneyEnrollment>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, ServiceJourneyEnrollment>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_user_id');
    }

    /**
     * @return HasMany<ServiceJourneyMilestoneAward>
     */
    public function milestoneAwards(): HasMany
    {
        return $this->hasMany(ServiceJourneyMilestoneAward::class);
    }

    /**
     * @return HasMany<ServiceJourneyPointEvent>
     */
    public function pointEvents(): HasMany
    {
        return $this->hasMany(ServiceJourneyPointEvent::class);
    }
}
