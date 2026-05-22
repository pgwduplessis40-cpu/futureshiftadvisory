<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Scenario extends Model
{
    use HasUuids;

    public const KIND_BEST = 'best';

    public const KIND_EXPECTED = 'expected';

    public const KIND_WORST = 'worst';

    public const KIND_CUSTOM = 'custom';

    protected $guarded = [];

    protected $casts = [
        'assumptions' => 'array',
        'economic_overlay' => 'array',
        'pv_impact' => 'float',
        'position' => 'integer',
        'is_client_visible' => 'boolean',
    ];

    /**
     * @return array<int, string>
     */
    public static function kinds(): array
    {
        return [
            self::KIND_BEST,
            self::KIND_EXPECTED,
            self::KIND_WORST,
            self::KIND_CUSTOM,
        ];
    }

    /**
     * @return BelongsTo<Client, Scenario>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<AnalysisRun, Scenario>
     */
    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    /**
     * @return BelongsTo<PvCalculation, Scenario>
     */
    public function pvCalculation(): BelongsTo
    {
        return $this->belongsTo(PvCalculation::class);
    }

    /**
     * @return BelongsTo<User, Scenario>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
