<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiscountMethod;
use App\Enums\PvType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PvCalculation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'type' => PvType::class,
        'discount_method' => DiscountMethod::class,
        'discount_rate' => 'float',
        'inputs' => 'array',
        'result' => 'array',
        'as_at' => 'datetime',
        'source_attributions' => 'array',
    ];

    /**
     * @return BelongsTo<Client, PvCalculation>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, PvCalculation>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
