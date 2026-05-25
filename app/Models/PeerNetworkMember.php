<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PeerNetworkMember extends Model
{
    use HasUuids;

    public const COMMUNITY_SME = 'sme';

    public const COMMUNITY_ENTREPRENEUR = 'entrepreneur';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REVOKED = 'revoked';

    protected $guarded = [];

    protected $casts = [
        'joined_at' => 'datetime',
        'suspended_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, PeerNetworkMember>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Consent, PeerNetworkMember>
     */
    public function consent(): BelongsTo
    {
        return $this->belongsTo(Consent::class);
    }
}
