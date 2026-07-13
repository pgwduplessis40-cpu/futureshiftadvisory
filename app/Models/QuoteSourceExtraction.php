<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class QuoteSourceExtraction extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_EXTRACTED = 'extracted';

    public const STATUS_BLOCKED = 'blocked';

    protected $guarded = [];

    protected $casts = [
        'extracted_rows' => 'array',
        'confirmed_row_ids' => 'array',
        'description_captured_at' => 'datetime',
        'extracted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, QuoteSourceExtraction>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, QuoteSourceExtraction>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<QuoteSourceExtractionDocument>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(QuoteSourceExtractionDocument::class);
    }
}
