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

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'user_id',
        'role',
        'granted_modules',
        'advisor_team_id',
    ];

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

    /**
     * @return BelongsTo<AdvisorTeam, ClientTeamMember>
     */
    public function advisorTeam(): BelongsTo
    {
        return $this->belongsTo(AdvisorTeam::class);
    }
}
