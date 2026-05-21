<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnalysisLens;
use App\Enums\FindingSeverity;
use App\Services\Ai\Contracts\Uncertainty;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AnalysisFinding extends Model
{
    use HasUuids;

    public const DOCUMENT_SUPPORT_VERIFIED = 'verified';

    public const DOCUMENT_SUPPORT_ADVISORY_FLAG = 'advisory_flag';

    public const DOCUMENT_SUPPORT_ACCURACY_DISCREPANCY = 'accuracy_discrepancy';

    public const DOCUMENT_SUPPORT_NONE = 'none';

    protected $guarded = [];

    protected $casts = [
        'lens' => AnalysisLens::class,
        'severity' => FindingSeverity::class,
        'attributions' => 'array',
        'bias_signals' => 'array',
        'uncertainty' => Uncertainty::class,
    ];

    /**
     * @return BelongsTo<AnalysisRun, AnalysisFinding>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class, 'analysis_run_id');
    }

    /**
     * @return BelongsTo<Client, AnalysisFinding>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<AnalysisFeedback>
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(AnalysisFeedback::class);
    }
}
