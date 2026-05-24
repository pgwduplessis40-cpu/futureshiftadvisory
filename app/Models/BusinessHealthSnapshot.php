<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BusinessHealthSnapshot extends Model
{
    use HasUuids;

    public const DIMENSION_FINANCIAL = 'financial';

    public const DIMENSION_OPERATIONAL = 'operational';

    public const DIMENSION_PEOPLE = 'people';

    public const DIMENSION_STRATEGIC = 'strategic';

    public const DIMENSION_COMPLIANCE = 'compliance';

    public const STATE_SCORED = 'scored';

    public const STATE_COMPLETED_NO_FINDINGS = 'completed_no_findings';

    public const STATE_COMPLETED_NO_CLIENT_SAFE_FINDINGS = 'completed_no_client_safe_findings';

    public const STATE_NEVER_RUN = 'never_run';

    public const STATE_IN_PROGRESS = 'in_progress';

    public const STATE_BLOCKED = 'blocked';

    public const STATE_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'score' => 'integer',
        'contributing_finding_ids' => 'array',
        'module_run_states' => 'array',
        'source_attributions' => 'array',
        'captured_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public static function dimensions(): array
    {
        return [
            self::DIMENSION_FINANCIAL,
            self::DIMENSION_OPERATIONAL,
            self::DIMENSION_PEOPLE,
            self::DIMENSION_STRATEGIC,
            self::DIMENSION_COMPLIANCE,
        ];
    }

    /**
     * @return BelongsTo<Client, BusinessHealthSnapshot>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<AnalysisFinding, BusinessHealthSnapshot>
     */
    public function topFinding(): BelongsTo
    {
        return $this->belongsTo(AnalysisFinding::class, 'top_finding_id');
    }
}
