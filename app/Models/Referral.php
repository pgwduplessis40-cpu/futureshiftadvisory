<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Referral extends Model
{
    use HasUuids;

    public const TYPE_BROKER = 'broker_referral';

    public const TYPE_COACH = 'coach_referral';

    public const STAGE_DRAFT = 'draft';

    public const STAGE_SENT = 'sent';

    public const STAGE_ACCEPTED = 'accepted';

    public const STAGE_IN_PROGRESS = 'in_progress';

    public const STAGE_COMPLETED = 'completed';

    public const STAGE_WITHDRAWN = 'withdrawn';

    public const STAGE_BROKER_REFERRAL_SENT = 'referral_sent';

    public const STAGE_BROKER_ACKNOWLEDGED = 'broker_acknowledged';

    public const STAGE_BROKER_QUOTE_REQUESTED = 'quote_requested';

    public const STAGE_BROKER_COVER_PLACED = 'cover_placed';

    public const STAGE_BROKER_DECLINED = 'declined';

    public const STAGE_BROKER_NO_RESPONSE = 'no_response';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public static function stages(): array
    {
        return array_values(array_unique([
            self::STAGE_DRAFT,
            self::STAGE_SENT,
            self::STAGE_ACCEPTED,
            self::STAGE_IN_PROGRESS,
            self::STAGE_COMPLETED,
            self::STAGE_WITHDRAWN,
            ...self::brokerStages(),
        ]));
    }

    /**
     * @return array<int, string>
     */
    public static function brokerStages(): array
    {
        return [
            self::STAGE_DRAFT,
            self::STAGE_BROKER_REFERRAL_SENT,
            self::STAGE_BROKER_ACKNOWLEDGED,
            self::STAGE_BROKER_QUOTE_REQUESTED,
            self::STAGE_BROKER_COVER_PLACED,
            self::STAGE_BROKER_DECLINED,
            self::STAGE_BROKER_NO_RESPONSE,
            self::STAGE_WITHDRAWN,
        ];
    }

    /**
     * @return BelongsTo<Client, Referral>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<PanelMember, Referral>
     */
    public function panelMember(): BelongsTo
    {
        return $this->belongsTo(PanelMember::class);
    }

    /**
     * @return HasMany<ReferralMessage>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ReferralMessage::class);
    }
}
