<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NpoFunderReportSession extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'accessed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<NpoFunderReportLink, NpoFunderReportSession>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(NpoFunderReportLink::class, 'npo_funder_report_link_id');
    }

    /**
     * @return BelongsTo<Report, NpoFunderReportSession>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
