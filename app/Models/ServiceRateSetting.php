<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ServiceRateSetting extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'hourly_rate' => 'float',
        'npo_service_discount_percent' => 'float',
        'npo_retainer_discount_percent' => 'float',
        'effective_from' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, ServiceRateSetting>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
