<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdvisorTeamMember extends Model
{
    use HasUuids;

    public const ROLE_LEAD = 'lead';

    public const ROLE_MEMBER = 'member';

    public const ROLE_OPERATIONS = 'operations';

    protected $guarded = [];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AdvisorTeam, AdvisorTeamMember>
     */
    public function advisorTeam(): BelongsTo
    {
        return $this->belongsTo(AdvisorTeam::class);
    }

    /**
     * @return BelongsTo<User, AdvisorTeamMember>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
