<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PracticeHealthSnapshot extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'client_ids' => 'array',
        'metrics' => 'array',
        'generated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, PracticeHealthSnapshot>
     */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_user_id');
    }
}
