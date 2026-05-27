<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

final class NpoValueCalculation extends Model
{
    use HasUuids;

    public const TYPE_COST_PER_BENEFICIARY = 'cost_per_beneficiary';

    public const TYPE_FUNDING_RISK = 'funding_risk';

    public const UNCERTAINTY_RATE = 0.15;

    protected $guarded = [];

    protected $casts = [
        'dimension_number' => 'integer',
        'projection_mid' => 'float',
        'projection_low' => 'float',
        'projection_high' => 'float',
        'inputs' => 'array',
        'result' => 'array',
        'benchmark_config' => 'array',
        'source_attributions' => 'array',
        'calculated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        self::saving(function (NpoValueCalculation $calculation): void {
            $calculation->assertMandatoryUncertainty();
        });
    }

    /**
     * @return BelongsTo<Client, NpoValueCalculation>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<NpoEngagement, NpoValueCalculation>
     */
    public function npoEngagement(): BelongsTo
    {
        return $this->belongsTo(NpoEngagement::class);
    }

    private function assertMandatoryUncertainty(): void
    {
        if (trim((string) $this->stable_assumption_disclosure) === '') {
            throw new InvalidArgumentException('NPO value calculations require a stable-assumption disclosure.');
        }

        $projections = $this->result['projections'] ?? null;
        if (! is_array($projections) || $projections === []) {
            throw new InvalidArgumentException('NPO value calculations require a +/-15% uncertainty range.');
        }

        foreach ($projections as $projection) {
            if (! is_array($projection)) {
                throw new InvalidArgumentException('NPO value calculation projections must be structured.');
            }

            $uncertainty = $projection['uncertainty'] ?? null;
            $hasRange = array_key_exists('low', $projection)
                && array_key_exists('mid', $projection)
                && array_key_exists('high', $projection);

            if (! is_array($uncertainty) || ! $hasRange || (float) ($uncertainty['rate'] ?? 0) !== self::UNCERTAINTY_RATE) {
                throw new InvalidArgumentException('NPO value calculations require a +/-15% uncertainty range.');
            }
        }
    }
}
