<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DdEngagement extends Model
{
    use HasUuids;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_ACQUISITION_PROCEEDING = 'acquisition_proceeding';

    public const STATUS_ABANDONED = 'abandoned';

    public const RECOMMENDATION_PROCEED = 'proceed';

    public const RECOMMENDATION_RENEGOTIATE = 'renegotiate';

    public const RECOMMENDATION_ABANDON = 'abandon';

    protected $guarded = [];

    protected $casts = [
        'target_details' => 'array',
        'disclaimer_acknowledged_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, DdEngagement>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<ConflictDeclaration, DdEngagement>
     */
    public function conflictDeclaration(): BelongsTo
    {
        return $this->belongsTo(ConflictDeclaration::class);
    }

    /**
     * @return BelongsTo<User, DdEngagement>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
