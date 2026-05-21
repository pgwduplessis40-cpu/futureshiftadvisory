<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommunicationPreference extends Model
{
    use HasUuids;

    public const CHANNEL_EMAIL_ONLY = 'email_only';

    public const CHANNEL_IN_PLATFORM_ONLY = 'in_platform_only';

    public const CHANNEL_BOTH = 'both';

    public const FREQUENCY_IMMEDIATE = 'immediate';

    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

    protected $guarded = [];

    /**
     * @return BelongsTo<User, CommunicationPreference>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<int, string>
     */
    public static function channels(): array
    {
        return [
            self::CHANNEL_EMAIL_ONLY,
            self::CHANNEL_IN_PLATFORM_ONLY,
            self::CHANNEL_BOTH,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function frequencies(): array
    {
        return [
            self::FREQUENCY_IMMEDIATE,
            self::FREQUENCY_DAILY,
            self::FREQUENCY_WEEKLY,
        ];
    }
}
