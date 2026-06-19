<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SurveyQuestionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SurveyQuestion extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'type' => SurveyQuestionType::class,
        'options' => 'array',
        'required' => 'boolean',
    ];

    /**
     * @return BelongsTo<Survey, SurveyQuestion>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * @return HasMany<SurveyAnswer>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class, 'question_id');
    }
}
