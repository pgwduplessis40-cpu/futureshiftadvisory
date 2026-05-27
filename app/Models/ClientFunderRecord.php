<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ClientFunderRecord extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'grant_amount' => 'float',
        'period_start' => 'date',
        'period_end' => 'date',
        'conditions' => 'array',
        'reporting_deadline' => 'date',
        'next_application_window_opens_at' => 'date',
        'next_application_window_closes_at' => 'date',
        'grant_expiry_at' => 'date',
        'renewal_probability' => 'integer',
        'history' => 'array',
    ];

    /**
     * @return BelongsTo<Client, ClientFunderRecord>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<NpoEngagement, ClientFunderRecord>
     */
    public function npoEngagement(): BelongsTo
    {
        return $this->belongsTo(NpoEngagement::class);
    }

    /**
     * @return BelongsTo<Funder, ClientFunderRecord>
     */
    public function funder(): BelongsTo
    {
        return $this->belongsTo(Funder::class);
    }

    /**
     * @return HasMany<ClientFunderAlert>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(ClientFunderAlert::class);
    }
}
