<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NpoComplianceAlert extends Model
{
    use HasUuids;

    public const TYPE_ISA_2022_REREGISTRATION_MISSING = 'isa_2022_reregistration_missing';

    public const SEVERITY_CRITICAL = 'critical';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, NpoComplianceAlert>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<NpoEngagement, NpoComplianceAlert>
     */
    public function npoEngagement(): BelongsTo
    {
        return $this->belongsTo(NpoEngagement::class);
    }

    /**
     * @return BelongsTo<User, NpoComplianceAlert>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function blocksAnalysis(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL
            && $this->acknowledged_at === null
            && $this->resolved_at === null;
    }
}
