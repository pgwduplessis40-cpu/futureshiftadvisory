<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Learning\LayerCadenceRegistry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

final class Funder extends Model
{
    use HasUuids;

    public const TYPE_GOVERNMENT = 'government';

    public const TYPE_PHILANTHROPIC = 'philanthropic';

    public const TYPE_CORPORATE = 'corporate';

    public const TYPE_COMMUNITY = 'community';

    private static int $governanceBypass = 0;

    protected $guarded = [];

    protected $casts = [
        'funding_windows' => 'array',
        'criteria' => 'array',
        'reporting_requirements' => 'array',
        'renewal_intelligence' => 'array',
        'last_verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        self::saving(function (Funder $funder): void {
            $funder->assertLayer34Governance();
        });
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutGovernance(callable $callback): mixed
    {
        self::$governanceBypass++;

        try {
            return $callback();
        } finally {
            self::$governanceBypass--;
        }
    }

    /**
     * @return HasMany<ClientFunderRecord>
     */
    public function clientRecords(): HasMany
    {
        return $this->hasMany(ClientFunderRecord::class);
    }

    /**
     * @return BelongsTo<LearningUpdate, Funder>
     */
    public function sourceLearningUpdate(): BelongsTo
    {
        return $this->belongsTo(LearningUpdate::class, 'source_learning_update_id');
    }

    public function needsVerification(): bool
    {
        return ! $this->last_verified_at instanceof \DateTimeInterface
            || $this->last_verified_at->lte(now()->subMonthsNoOverflow(12));
    }

    private function assertLayer34Governance(): void
    {
        if (self::$governanceBypass > 0) {
            return;
        }

        $updateId = $this->source_learning_update_id;
        $approved = is_string($updateId)
            && LearningUpdate::query()
                ->whereKey($updateId)
                ->where('layer_id', LayerCadenceRegistry::LAYER_NPO_FUNDER_DATABASE_UPDATES)
                ->whereIn('status', [
                    LearningUpdate::STATUS_APPROVED,
                    LearningUpdate::STATUS_IMPLEMENTED,
                ])
                ->exists();

        if (! $approved) {
            throw new RuntimeException('Funder registry changes require an approved Layer 34 learning update.');
        }
    }
}
