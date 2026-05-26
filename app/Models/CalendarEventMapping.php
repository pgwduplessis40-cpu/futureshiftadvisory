<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CalendarEventMapping extends Model
{
    use HasUuids;

    public const DIRECTION_PUSH = 'push';

    public const DIRECTION_PULL = 'pull';

    public const DIRECTION_TWO_WAY = 'two_way';

    public const ORIGIN_FSA = 'fsa';

    public const ORIGIN_EXTERNAL = 'external';

    protected $guarded = [];

    protected $casts = [
        'provider_updated_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'attendees' => 'array',
        'is_external_only' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public static function directions(): array
    {
        return [
            self::DIRECTION_PUSH,
            self::DIRECTION_PULL,
            self::DIRECTION_TWO_WAY,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function origins(): array
    {
        return [
            self::ORIGIN_FSA,
            self::ORIGIN_EXTERNAL,
        ];
    }

    /**
     * @return BelongsTo<CalendarConnection, CalendarEventMapping>
     */
    public function calendarConnection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class);
    }

    /**
     * @return BelongsTo<Meeting, CalendarEventMapping>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
