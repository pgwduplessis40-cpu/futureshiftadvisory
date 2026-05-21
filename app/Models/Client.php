<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EngagementType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class Client extends Model
{
    use HasUuids;

    public const DATA_QUALITY_HIGH = 'high';

    public const DATA_QUALITY_MEDIUM = 'medium';

    public const DATA_QUALITY_LOW = 'low';

    public const DATA_QUALITY_INSUFFICIENT = 'insufficient';

    protected $guarded = [];

    protected $casts = [
        'engagement_type' => EngagementType::class,
        'address' => 'array',
        'gst_registered' => 'boolean',
        'directors' => 'array',
        'registry_sources' => 'array',
        'onboarding_wizard_state' => 'array',
        'engagement_type_locked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, Client>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, Client>
     */
    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_contact_user_id');
    }

    /**
     * @return HasMany<ClientTeamMember>
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(ClientTeamMember::class);
    }

    /**
     * @return HasMany<ConflictDeclaration>
     */
    public function conflictDeclarations(): HasMany
    {
        return $this->hasMany(ConflictDeclaration::class);
    }

    /**
     * @return HasMany<WellbeingCheckin>
     */
    public function wellbeingCheckins(): HasMany
    {
        return $this->hasMany(WellbeingCheckin::class);
    }

    /**
     * @return HasMany<CoachingSignal>
     */
    public function coachingSignals(): HasMany
    {
        return $this->hasMany(CoachingSignal::class);
    }

    public function engagementTypeIsLocked(): bool
    {
        if ($this->engagement_type_locked_at !== null) {
            return true;
        }

        if (! $this->exists || ! Schema::hasTable('questionnaire_responses')) {
            return false;
        }

        return DB::table('questionnaire_responses')
            ->where('client_id', $this->getKey())
            ->exists();
    }
}
