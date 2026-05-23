<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReverseReferral extends Model
{
    use HasUuids;

    public const TARGET_PROSPECT = 'prospect';

    public const TARGET_ENTREPRENEUR = 'entrepreneur';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'submitted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PanelMember, ReverseReferral>
     */
    public function panelMember(): BelongsTo
    {
        return $this->belongsTo(PanelMember::class);
    }

    /**
     * @return BelongsTo<ProspectLead, ReverseReferral>
     */
    public function prospectLead(): BelongsTo
    {
        return $this->belongsTo(ProspectLead::class);
    }

    /**
     * @return BelongsTo<EntrepreneurProfile, ReverseReferral>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }
}
