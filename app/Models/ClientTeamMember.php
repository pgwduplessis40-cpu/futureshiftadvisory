<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClientTeamMember extends Model
{
    use HasUuids;

    protected $table = 'client_team';

    protected $guarded = [];

    protected $casts = [
        'granted_modules' => 'array',
    ];

    /**
     * @return BelongsTo<Client, ClientTeamMember>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, ClientTeamMember>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
