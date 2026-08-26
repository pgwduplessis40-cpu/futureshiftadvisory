<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdvisorApiClient extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const SCOPE_READ_CLIENTS = 'clients:read';

    public const SCOPE_WRITE_MEETING_NOTES = 'meeting_notes:write';

    public const SCOPE_WRITE_ACTIONS = 'actions:write';

    /** @var list<string> */
    protected $fillable = [
        'advisor_user_id',
        'name',
        'token_hash',
        'scopes',
        'status',
        'rate_limit_per_minute',
        'approved_by_user_id',
        'approved_at',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'rate_limit_per_minute' => 'integer',
        'approved_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, AdvisorApiClient>
     */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_user_id');
    }

    /**
     * @return BelongsTo<User, AdvisorApiClient>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->approved_at !== null;
    }

    public function allows(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}
