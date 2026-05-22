<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FunnelEvent extends Model
{
    use HasUuids;

    public const FLOW_ONBOARDING = 'onboarding';

    public const FLOW_QUESTIONNAIRE = 'questionnaire';

    public const FLOW_PROPOSAL = 'proposal';

    protected $guarded = [];

    protected $casts = [
        'entered_at' => 'datetime',
        'completed_at' => 'datetime',
        'abandoned' => 'boolean',
    ];

    /**
     * @return BelongsTo<Client, FunnelEvent>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, FunnelEvent>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
