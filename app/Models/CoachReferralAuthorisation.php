<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CoachReferralAuthorisation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, CoachReferralAuthorisation>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, CoachReferralAuthorisation>
     */
    public function authorisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorised_by_user_id');
    }
}
