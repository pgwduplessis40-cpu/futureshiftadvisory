<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuestionnaireAnswer extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'value' => 'array',
        'attached_document_ids' => 'array',
    ];

    /**
     * @return BelongsTo<QuestionnaireResponse, QuestionnaireAnswer>
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireResponse::class);
    }

    /**
     * @return BelongsTo<QuestionnaireQuestion, QuestionnaireAnswer>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireQuestion::class);
    }
}
