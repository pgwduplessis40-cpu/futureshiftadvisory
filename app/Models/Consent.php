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

    public const TYPE_BENCHMARK_COMMUNITY = 'benchmark_community';

    public const TYPE_PEER_NETWORK = 'peer_network';

    public const TYPE_WHISPER_TRANSCRIPTION = 'whisper_transcription';

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
        return [
            self::TYPE_INSURANCE_REFERRAL,
            self::TYPE_COACH_REFERRAL,
            self::TYPE_BENCHMARK_COMMUNITY,
            self::TYPE_PEER_NETWORK,
            self::TYPE_WHISPER_TRANSCRIPTION,
        ];
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

    /**
     * @return BelongsTo<User, Consent>
     */
    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function isActiveOptIn(): bool
    {
        return $this->election === self::ELECTION_OPT_IN && $this->revoked_at === null;
    }
}
