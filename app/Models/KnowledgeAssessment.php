<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KnowledgeAssessment extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'financial_literacy' => 'integer',
        'strategic_awareness' => 'integer',
        'leadership' => 'integer',
        'calibration' => 'array',
        'assessed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, KnowledgeAssessment>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, KnowledgeAssessment>
     */
    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id');
    }
}
