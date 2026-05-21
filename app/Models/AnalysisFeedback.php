<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AnalysisFeedback extends Model
{
    use HasUuids;

    public const DECISION_CONFIRM = 'confirm';

    public const DECISION_CORRECT = 'correct';

    public const DECISION_RATE = 'rate';

    public const DECISION_ADD_CONTEXT = 'add_context';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'rating' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AnalysisFinding, AnalysisFeedback>
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(AnalysisFinding::class, 'analysis_finding_id');
    }

    /**
     * @return BelongsTo<User, AnalysisFeedback>
     */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_user_id');
    }
}
