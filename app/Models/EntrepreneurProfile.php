<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntrepreneurStage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EntrepreneurProfile extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'stage' => EntrepreneurStage::class,
    ];

    /**
     * @return BelongsTo<User, EntrepreneurProfile>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, EntrepreneurProfile>
     */
    public function assignedAdvisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_advisor_id');
    }

    /**
     * @return BelongsTo<InviteToken, EntrepreneurProfile>
     */
    public function inviteToken(): BelongsTo
    {
        return $this->belongsTo(InviteToken::class);
    }
}
