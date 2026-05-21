<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class QuestionnaireResponse extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, QuestionnaireResponse>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Questionnaire, QuestionnaireResponse>
     */
    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    /**
     * @return BelongsTo<User, QuestionnaireResponse>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * @return HasMany<QuestionnaireAnswer>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(QuestionnaireAnswer::class, 'response_id');
    }
}
