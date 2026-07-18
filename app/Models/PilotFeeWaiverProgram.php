<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PilotFeeWaiverProgram extends Model
{
    use HasUuids;

    public const KEY_DEFAULT = 'pilot-fee-waiver';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_SUSPENDED = 'suspended';

    protected $guarded = [];

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_CLOSED,
            self::STATUS_SUSPENDED,
        ];
    }

    public function allowsNewWaivers(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * @return BelongsTo<User, PilotFeeWaiverProgram>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
