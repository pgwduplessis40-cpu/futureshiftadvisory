<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReportSection extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'report_id',
        'client_id',
        'entrepreneur_profile_id',
        'key',
        'title',
        'body',
        'position',
        'lens',
        'attributions',
        'document_support',
        'document_support_note',
        'data_quality_note',
        'metadata',
    ];

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

    /**
     * @return BelongsTo<EntrepreneurProfile, ReportSection>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return HasMany<ReportSectionRevision>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(ReportSectionRevision::class);
    }

    /**
     * @return HasMany<ReportSectionComment>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ReportSectionComment::class);
    }
}
