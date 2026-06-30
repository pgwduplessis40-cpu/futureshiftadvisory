<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReferenceDataEntry extends Model
{
    use HasUuids;

    public const DATASET_ECONOMIC_INDICATOR = 'economic_indicator';

    public const DATASET_VALUATION_MULTIPLE = 'valuation_multiple';

    public const DATASET_INDUSTRY_WACC = 'industry_wacc';

    public const DATASET_CPB_BENCHMARK = 'cpb_benchmark';

    public const DATASET_GST_RATE = 'gst_rate';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'as_at' => 'date',
    ];

    /**
     * @return array<int, string>
     */
    public static function datasets(): array
    {
        return [
            self::DATASET_ECONOMIC_INDICATOR,
            self::DATASET_VALUATION_MULTIPLE,
            self::DATASET_INDUSTRY_WACC,
            self::DATASET_CPB_BENCHMARK,
            self::DATASET_GST_RATE,
        ];
    }

    /**
     * @return BelongsTo<LearningUpdate, ReferenceDataEntry>
     */
    public function learningUpdate(): BelongsTo
    {
        return $this->belongsTo(LearningUpdate::class);
    }

    /**
     * @return BelongsTo<Document, ReferenceDataEntry>
     */
    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'evidence_document_id');
    }

    /**
     * @return BelongsTo<User, ReferenceDataEntry>
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }
}
