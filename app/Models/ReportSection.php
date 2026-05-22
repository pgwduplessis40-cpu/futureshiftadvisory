<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReportSection extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'position' => 'integer',
        'attributions' => 'array',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Report, ReportSection>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * @return BelongsTo<Client, ReportSection>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
