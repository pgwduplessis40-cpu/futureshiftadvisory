<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IdeaValidation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'ai_evaluation' => 'array',
        'viability_alerts' => 'array',
        'revision_number' => 'integer',
        'evaluated_at' => 'datetime',
        'advisor_gate_passed_at' => 'datetime',
        'recalled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EntrepreneurProfile, IdeaValidation>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<IdeaValidation, IdeaValidation>
     */
    public function previousValidation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_validation_id');
    }

    /**
     * @return BelongsTo<User, IdeaValidation>
     */
    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by_user_id');
    }

    /**
     * @return BelongsTo<User, IdeaValidation>
     */
    public function advisorGatePassedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_gate_passed_by_user_id');
    }
}
