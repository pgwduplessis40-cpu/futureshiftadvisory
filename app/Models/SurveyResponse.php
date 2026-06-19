<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SurveyResponse extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
        'overall_score' => 'float',
        'nps_score' => 'integer',
    ];

    /**
     * @return BelongsTo<SurveyAssignment, SurveyResponse>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SurveyAssignment::class, 'survey_assignment_id');
    }

    /**
     * @return BelongsTo<Survey, SurveyResponse>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * @return BelongsTo<Client, SurveyResponse>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<EntrepreneurProfile, SurveyResponse>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<User, SurveyResponse>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * @return HasMany<SurveyAnswer>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class, 'response_id');
    }
}
