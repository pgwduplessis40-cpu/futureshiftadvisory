<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RedFlag extends Model
{
    use HasUuids;

    public const CATEGORY_FINANCIAL = 'financial';

    public const CATEGORY_COMPLIANCE = 'compliance';

    public const CATEGORY_KEY_PERSON = 'key_person';

    public const CATEGORY_INSURANCE = 'insurance';

    public const CATEGORY_VIABILITY = 'viability';

    public const CATEGORY_REGULATORY = 'regulatory';

    protected $guarded = [];

    protected $casts = [
        'surfaced_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, RedFlag>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<AnalysisFinding, RedFlag>
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(AnalysisFinding::class, 'analysis_finding_id');
    }

    /**
     * @return BelongsTo<User, RedFlag>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    /**
     * @return array<int, string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_FINANCIAL,
            self::CATEGORY_COMPLIANCE,
            self::CATEGORY_KEY_PERSON,
            self::CATEGORY_INSURANCE,
            self::CATEGORY_VIABILITY,
            self::CATEGORY_REGULATORY,
        ];
    }
}
