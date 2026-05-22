<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProofOfCompletion extends Model
{
    use HasUuids;

    protected $table = 'proof_of_completion';

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FLAGGED = 'flagged';

    protected $guarded = [];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Milestone, ProofOfCompletion>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * @return BelongsTo<Client, ProofOfCompletion>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Document, ProofOfCompletion>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<DocumentVerification, ProofOfCompletion>
     */
    public function documentVerification(): BelongsTo
    {
        return $this->belongsTo(DocumentVerification::class);
    }
}
