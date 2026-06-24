<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TermsEnforcement extends Model
{
    use HasUuids;

    public const SCOPE_PLATFORM = 'platform';

    protected $guarded = [];

    protected $casts = [
        'activated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, TermsEnforcement>
     */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }
}
