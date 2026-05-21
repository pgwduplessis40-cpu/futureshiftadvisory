<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ConflictDeclaration extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'declaration' => 'array',
        'declared_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, ConflictDeclaration>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, ConflictDeclaration>
     */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function referralType(): ?string
    {
        $type = $this->declaration['referral_type'] ?? null;

        return is_string($type) && $type !== '' ? $type : null;
    }

    public function isFreshFor(int $days): bool
    {
        return $this->declared_at !== null
            && $this->declared_at->greaterThanOrEqualTo(now()->subDays($days));
    }
}
