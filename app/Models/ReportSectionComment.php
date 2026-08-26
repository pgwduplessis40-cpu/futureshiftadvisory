<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReportSectionComment extends Model
{
    use HasUuids;

    public const VISIBILITY_ADVISOR_ONLY = 'advisor_only';

    public const VISIBILITY_CLIENT_VISIBLE = 'client_visible';

    /** @var list<string> */
    protected $fillable = [
        'report_id',
        'report_section_id',
        'client_id',
        'entrepreneur_profile_id',
        'visibility',
        'body',
        'created_by_user_id',
        'resolved_at',
        'resolved_by_user_id',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * @return BelongsTo<ReportSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(ReportSection::class, 'report_section_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
