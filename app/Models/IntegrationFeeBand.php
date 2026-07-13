<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IntegrationFeeBand extends Model
{
    use HasUuids;

    public const BAND_S = 'S';

    public const BAND_M = 'M';

    public const BAND_L = 'L';

    public const BAND_XL = 'XL';

    protected $guarded = [];

    protected $casts = [
        'fee_low' => 'float',
        'fee_mid' => 'float',
        'fee_high' => 'float',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, IntegrationFeeBand>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
