<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProposalSignoffStep extends Model
{
    use HasUuids;

    public const STEP_REVIEW = 'review';

    public const STEP_INSURANCE_CONSENT = 'insurance_consent';

    public const STEP_COACH_CONSENT = 'coach_consent';

    public const STEP_PAYMENT_METHOD = 'payment_method';

    public const STEP_AUTHORITY = 'authority';

    public const STEP_SIGNATURE = 'signature';

    public const STEP_CONFIRMATION = 'confirmation';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Proposal, ProposalSignoffStep>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<Client, ProposalSignoffStep>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, ProposalSignoffStep>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    /**
     * @return array<int, string>
     */
    public static function orderedSteps(): array
    {
        return [
            self::STEP_REVIEW,
            self::STEP_INSURANCE_CONSENT,
            self::STEP_COACH_CONSENT,
            self::STEP_PAYMENT_METHOD,
            self::STEP_AUTHORITY,
            self::STEP_SIGNATURE,
            self::STEP_CONFIRMATION,
        ];
    }
}
