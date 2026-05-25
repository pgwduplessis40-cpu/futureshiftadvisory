<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ConversionOutcome extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'outcome_signal' => 'array',
        'observed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EntrepreneurProfile, ConversionOutcome>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<Client, ConversionOutcome>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<PlanAssessment, ConversionOutcome>
     */
    public function planAssessment(): BelongsTo
    {
        return $this->belongsTo(PlanAssessment::class);
    }
}
