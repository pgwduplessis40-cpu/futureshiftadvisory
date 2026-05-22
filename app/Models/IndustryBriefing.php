<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IndustryBriefing extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    protected $guarded = [];

    protected $casts = [
        'period' => 'date',
        'sources' => 'array',
        'reviewed_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, IndustryBriefing>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, IndustryBriefing>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
