<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SurveyAssignmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class SurveyAssignment extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'status' => SurveyAssignmentStatus::class,
        'activated_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'deliverable_snapshot' => 'array',
    ];

    /**
     * @return BelongsTo<Survey, SurveyAssignment>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * @return BelongsTo<Client, SurveyAssignment>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<EntrepreneurProfile, SurveyAssignment>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<User, SurveyAssignment>
     */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    /**
     * @return HasOne<SurveyResponse>
     */
    public function response(): HasOne
    {
        return $this->hasOne(SurveyResponse::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status?->value, SurveyAssignmentStatus::activeValues(), true);
    }
}
