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
}
