<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DdEngagement extends Model
{
    use HasUuids;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_ACQUISITION_PROCEEDING = 'acquisition_proceeding';

    public const STATUS_ABANDONED = 'abandoned';

    public const RECOMMENDATION_PROCEED = 'proceed';

    public const RECOMMENDATION_RENEGOTIATE = 'renegotiate';

    public const RECOMMENDATION_ABANDON = 'abandon';

    protected $guarded = [];

    protected $casts = [
        'target_details' => 'array',
        'disclaimer_acknowledged_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, DdEngagement>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<ConflictDeclaration, DdEngagement>
     */
    public function conflictDeclaration(): BelongsTo
    {
        return $this->belongsTo(ConflictDeclaration::class);
    }

    /**
     * @return BelongsTo<User, DdEngagement>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<DdDataRoomItem>
     */
    public function dataRoomItems(): HasMany
    {
        return $this->hasMany(DdDataRoomItem::class);
    }

    /**
     * @return HasMany<DdGuestLink>
     */
    public function guestLinks(): HasMany
    {
        return $this->hasMany(DdGuestLink::class);
    }

    /**
     * @return HasMany<DdWorkstream>
     */
    public function workstreams(): HasMany
    {
        return $this->hasMany(DdWorkstream::class);
    }

    /**
     * @return HasMany<DdValuation>
     */
    public function valuations(): HasMany
    {
        return $this->hasMany(DdValuation::class);
    }

    /**
     * @return HasMany<DdRiskRegisterItem>
     */
    public function riskRegisterItems(): HasMany
    {
        return $this->hasMany(DdRiskRegisterItem::class);
    }

    /**
     * @return HasMany<DdIntegrationPlanItem>
     */
    public function integrationPlanItems(): HasMany
    {
        return $this->hasMany(DdIntegrationPlanItem::class);
    }

    /**
     * @return HasMany<BusinessPlan>
     */
    public function businessPlans(): HasMany
    {
        return $this->hasMany(BusinessPlan::class);
    }
}
