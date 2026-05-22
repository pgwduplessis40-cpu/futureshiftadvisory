<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Report extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'type' => ReportType::class,
        'pdf_byte_size' => 'integer',
        'generated_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Client, Report>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, Report>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /**
     * @return HasMany<ReportSection>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(ReportSection::class);
    }
}
