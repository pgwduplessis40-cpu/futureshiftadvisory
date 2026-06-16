<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProjectSetting extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = [
        'value_envelope',
    ];

    protected $casts = [
        'is_secret' => 'boolean',
        'value_envelope_meta' => 'array',
    ];

    /**
     * @return BelongsTo<User, ProjectSetting>
     */
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id');
    }
}
