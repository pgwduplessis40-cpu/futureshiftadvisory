<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AdvisorTeam extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<User, AdvisorTeam>
     */
    public function leadAdvisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_advisor_user_id');
    }

    /**
     * @return HasMany<AdvisorTeamMember>
     */
    public function members(): HasMany
    {
        return $this->hasMany(AdvisorTeamMember::class);
    }

    /**
     * @return HasMany<ClientTeamMember>
     */
    public function clientTeamMembers(): HasMany
    {
        return $this->hasMany(ClientTeamMember::class);
    }
}
