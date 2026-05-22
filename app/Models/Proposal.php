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
    ];

    protected static function booted(): void
    {
        self::saving(function (Proposal $proposal): void {
            $status = $proposal->getAttribute('status');

            if ($status instanceof ProposalStatus && $status->phaseTwoReserved()) {
                throw new LogicException('Awaiting signature and signed proposal statuses are reserved for Phase 3.');
            }
        });
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
     * @return BelongsTo<User, Proposal>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<Proposal, Proposal>
     */
    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_proposal_id');
    }
}
