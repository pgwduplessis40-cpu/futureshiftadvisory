<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StrategicBudgetAssessment extends Model
{
    use HasUuids;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ASSESSED = 'assessed';

    public const STATUS_FEEDBACK_SAVED = 'feedback_saved';

    public const STATUS_FEEDBACK_SENT = 'feedback_sent';

    public const STATUS_APPROVED = 'approved';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'strategic_budget_id',
        'client_id',
        'round',
        'status',
        'snapshot',
        'assessment_criteria',
        'scores',
        'priorities',
        'suggested_feedback',
        'suggested_reply',
        'advisor_feedback',
        'proposed_reply',
        'feedback_snapshot',
        'submitted_at',
        'submitted_by_user_id',
        'assessed_at',
        'assessed_by_user_id',
        'feedback_saved_at',
        'feedback_saved_by_user_id',
        'feedback_sent_at',
        'feedback_sent_by_user_id',
        'client_message_thread_id',
        'client_message_id',
        'approved_at',
        'approved_by_user_id',
    ];

    protected $casts = [
        'round' => 'integer',
        'snapshot' => 'array',
        'assessment_criteria' => 'array',
        'scores' => 'array',
        'priorities' => 'array',
        'feedback_snapshot' => 'array',
        'submitted_at' => 'datetime',
        'assessed_at' => 'datetime',
        'feedback_saved_at' => 'datetime',
        'feedback_sent_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<StrategicBudget, StrategicBudgetAssessment>
     */
    public function strategicBudget(): BelongsTo
    {
        return $this->belongsTo(StrategicBudget::class);
    }

    /**
     * @return BelongsTo<Client, StrategicBudgetAssessment>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, StrategicBudgetAssessment>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * @return BelongsTo<User, StrategicBudgetAssessment>
     */
    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id');
    }

    /**
     * @return BelongsTo<User, StrategicBudgetAssessment>
     */
    public function feedbackSavedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'feedback_saved_by_user_id');
    }

    /**
     * @return BelongsTo<User, StrategicBudgetAssessment>
     */
    public function feedbackSentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'feedback_sent_by_user_id');
    }
}
