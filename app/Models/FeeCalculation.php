<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeeMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FeeCalculation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'method' => FeeMethod::class,
        'inputs' => 'array',
        'suggested_low' => 'float',
        'suggested_mid' => 'float',
        'suggested_high' => 'float',
        'improvement_pv_total' => 'float',
        'risk_cost_pv_total' => 'float',
        'roi_ratio' => 'float',
        'justification' => 'array',
    ];

    /**
     * @return BelongsTo<Client, FeeCalculation>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, FeeCalculation>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<Proposal>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }
}
