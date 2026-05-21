<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionnaireQuestionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuestionnaireQuestion extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'type' => QuestionnaireQuestionType::class,
        'options' => 'array',
        'conditional_logic' => 'array',
        'required' => 'boolean',
    ];

    /**
     * @return BelongsTo<QuestionnaireSection, QuestionnaireQuestion>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireSection::class, 'questionnaire_section_id');
    }
}
