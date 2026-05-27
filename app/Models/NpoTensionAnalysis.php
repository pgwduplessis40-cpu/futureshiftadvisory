<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

final class NpoTensionAnalysis extends Model
{
    use HasUuids;

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_REVIEWED = 'reviewed';

    public const TYPE_REVENUE_VS_ACCESS = 'revenue_vs_access';

    public const TYPE_MARGIN_VS_PAY_EQUITY = 'margin_vs_pay_equity';

    public const TYPE_FUNDER_RESTRICTION = 'funder_restriction';

    public const TYPE_GOVERNANCE_CONFLICT = 'governance_conflict';

    public const TYPE_KPI_MISALIGNMENT = 'kpi_misalignment';

    public const TYPE_GROWTH_TENSION = 'growth_tension';

    protected $guarded = [];

    protected $casts = [
        'tensions' => 'array',
        'ai_response' => 'array',
        'source_attributions' => 'array',
        'reviewed_at' => 'datetime',
        'generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        self::saving(function (NpoTensionAnalysis $analysis): void {
            $analysis->assertTensionEvidence();
        });
    }

    /**
     * @return array<int, string>
     */
    public static function allowedTypes(): array
    {
        return [
            self::TYPE_REVENUE_VS_ACCESS,
            self::TYPE_MARGIN_VS_PAY_EQUITY,
            self::TYPE_FUNDER_RESTRICTION,
            self::TYPE_GOVERNANCE_CONFLICT,
            self::TYPE_KPI_MISALIGNMENT,
            self::TYPE_GROWTH_TENSION,
        ];
    }

    /**
     * @return BelongsTo<Client, NpoTensionAnalysis>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<NpoEngagement, NpoTensionAnalysis>
     */
    public function npoEngagement(): BelongsTo
    {
        return $this->belongsTo(NpoEngagement::class);
    }

    /**
     * @return BelongsTo<NpoSocialEnterpriseScorecard, NpoTensionAnalysis>
     */
    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(NpoSocialEnterpriseScorecard::class, 'npo_social_enterprise_scorecard_id');
    }

    /**
     * @return BelongsTo<User, NpoTensionAnalysis>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function reviewed(): bool
    {
        return $this->review_status === self::REVIEW_REVIEWED && $this->reviewed_at !== null;
    }

    private function assertTensionEvidence(): void
    {
        if (! is_array($this->tensions) || count($this->tensions) < 1 || count($this->tensions) > 5) {
            throw new InvalidArgumentException('Social enterprise tension analysis requires between 1 and 5 tensions.');
        }

        foreach ($this->tensions as $tension) {
            if (! is_array($tension)) {
                throw new InvalidArgumentException('Social enterprise tensions must be structured.');
            }

            if (! in_array((string) ($tension['type'] ?? ''), self::allowedTypes(), true)) {
                throw new InvalidArgumentException('Social enterprise tension type is not supported.');
            }

            $dataPoints = $tension['data_points'] ?? null;
            if (! is_array($dataPoints) || $dataPoints === []) {
                throw new InvalidArgumentException('Every social enterprise tension must cite specific data points.');
            }
        }
    }
}
