<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WebsiteAuditSnapshot extends Model
{
    use HasUuids;

    public const STATUS_OK = 'ok';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_UNREACHABLE = 'unreachable';

    public const STATUS_SKIPPED_NO_URL = 'skipped_no_url';

    public const SKIP_NO_WEBSITE_URL_LISTED = 'no_website_url_listed';

    public const SKIP_AWAITING_ADVISOR_CONFIRMATION = 'awaiting_advisor_confirmation';

    protected $guarded = [];

    protected $casts = [
        'fetched_at' => 'datetime',
        'pages' => 'array',
        'ai_evidence' => 'array',
        'technical' => 'array',
        'performance' => 'array',
        'nz_compliance' => 'array',
        'scores' => 'array',
        'source_attributions' => 'array',
    ];

    /**
     * @return BelongsTo<Client, WebsiteAuditSnapshot>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<AnalysisRun, WebsiteAuditSnapshot>
     */
    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    /**
     * @return BelongsTo<WebsiteUrlConfirmation, WebsiteAuditSnapshot>
     */
    public function confirmation(): BelongsTo
    {
        return $this->belongsTo(WebsiteUrlConfirmation::class, 'website_url_confirmation_id');
    }
}
