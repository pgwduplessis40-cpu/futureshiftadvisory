<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WebsiteUrlConfirmation extends Model
{
    use HasUuids;

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PENDING_ADVISOR_REVIEW = 'pending_advisor_review';

    public const STATUS_REVOKED = 'revoked';

    protected $guarded = [];

    protected $casts = [
        'source_questionnaire_answer_ids' => 'array',
        'confirmed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, WebsiteUrlConfirmation>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, WebsiteUrlConfirmation>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    /**
     * @return HasMany<WebsiteAuditSnapshot>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(WebsiteAuditSnapshot::class);
    }
}
