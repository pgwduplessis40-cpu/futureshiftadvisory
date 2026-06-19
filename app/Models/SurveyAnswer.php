<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SurveyAnswer extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'anchor_ref' => 'array',
        'value' => 'array',
        'numeric_value' => 'float',
    ];

    /**
     * @return BelongsTo<SurveyResponse, SurveyAnswer>
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'response_id');
    }

    /**
     * @return BelongsTo<SurveyQuestion, SurveyAnswer>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'question_id');
    }

    /**
     * @return BelongsTo<Survey, SurveyAnswer>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }
}
