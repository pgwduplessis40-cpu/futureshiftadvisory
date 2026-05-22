<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProposalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class Proposal extends Model
{
    use HasUuids;

    private static bool $allowSignoffStatusTransition = false;

    protected $guarded = [];

    protected $casts = [
        'status' => ProposalStatus::class,
        'version' => 'integer',
        'scope' => 'array',
        'services' => 'array',
        'pv_summary' => 'array',
        'roi_ratio' => 'float',
        'acceptance_terms' => 'array',
        'pdf_byte_size' => 'integer',
        'released_at' => 'datetime',
        'expires_at' => 'datetime',
        'recalled_at' => 'datetime',
        'expired_at' => 'datetime',
        'awaiting_signature_at' => 'datetime',
        'signed_at' => 'datetime',
        'signature_envelope_meta' => 'array',
        'signature_evidence_byte_size' => 'integer',
    ];

    protected static function booted(): void
    {
        self::saving(function (Proposal $proposal): void {
            if (! $proposal->isDirty('status')) {
                return;
            }

            $status = $proposal->normalisedStatus($proposal->getAttribute('status'));

            if (! $status instanceof ProposalStatus || ! $status->requiresSignoffFlow()) {
                return;
            }

            if (! self::$allowSignoffStatusTransition) {
                throw new LogicException('Awaiting signature and signed proposal statuses are reachable only through the sign-off flow.');
            }

            $original = $proposal->normalisedStatus($proposal->getOriginal('status'));

            if ($status === ProposalStatus::AwaitingSignature && $original !== ProposalStatus::Released) {
                throw new LogicException('Awaiting signature requires a released proposal.');
            }

            if ($status === ProposalStatus::Signed && $original !== ProposalStatus::AwaitingSignature) {
                throw new LogicException('Signed proposals require the awaiting signature state first.');
            }
        });
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    public static function allowSignoffStatusTransition(callable $callback): mixed
    {
        $previous = self::$allowSignoffStatusTransition;
        self::$allowSignoffStatusTransition = true;

        try {
            return $callback();
        } finally {
            self::$allowSignoffStatusTransition = $previous;
        }
    }

    /**
     * @return BelongsTo<Client, Proposal>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<FeeCalculation, Proposal>
     */
    public function feeCalculation(): BelongsTo
    {
        return $this->belongsTo(FeeCalculation::class);
    }

    /**
     * @return HasMany<Consent>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * @return HasMany<ProposalSignoffStep>
     */
    public function signoffSteps(): HasMany
    {
        return $this->hasMany(ProposalSignoffStep::class);
    }

    /**
     * @return HasMany<PaymentAuthority>
     */
    public function paymentAuthorities(): HasMany
    {
        return $this->hasMany(PaymentAuthority::class);
    }

    /**
     * @return BelongsTo<User, Proposal>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, Proposal>
     */
    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }

    /**
     * @return BelongsTo<Proposal, Proposal>
     */
    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_proposal_id');
    }

    private function normalisedStatus(mixed $status): ?ProposalStatus
    {
        if ($status instanceof ProposalStatus) {
            return $status;
        }

        if (is_string($status) && $status !== '') {
            return ProposalStatus::tryFrom($status);
        }

        return null;
    }
}
