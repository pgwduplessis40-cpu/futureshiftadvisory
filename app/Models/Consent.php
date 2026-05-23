<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Consent extends Model
{
    use HasUuids;

    public const TYPE_INSURANCE_REFERRAL = 'insurance_referral';

    public const TYPE_COACH_REFERRAL = 'coach_referral';

    public const ELECTION_OPT_IN = 'opt_in';

    public const ELECTION_OPT_OUT = 'opt_out';

    public const ELECTION_UNDECIDED = 'undecided';

    protected $guarded = [];

    protected $casts = [
        'evidence' => 'array',
        'captured_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [self::TYPE_INSURANCE_REFERRAL, self::TYPE_COACH_REFERRAL];
    }

    /**
     * @return array<int, string>
     */
    public static function elections(): array
    {
        return [self::ELECTION_OPT_IN, self::ELECTION_OPT_OUT, self::ELECTION_UNDECIDED];
    }

    /**
     * @return BelongsTo<Client, Consent>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Proposal, Consent>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function isActiveOptIn(): bool
    {
        return $this->election === self::ELECTION_OPT_IN && $this->revoked_at === null;
    }
}
