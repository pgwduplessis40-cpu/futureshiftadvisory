<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class SecurityAudit extends Model
{
    use HasUuids;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_EVIDENCE_READY = 'evidence_ready';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_REMEDIATION = 'remediation';

    public const STATUS_CLOSED = 'closed';

    /** @var list<string> */
    protected $fillable = [
        'period',
        'auditor',
        'scope',
        'status',
        'evidence_manifest',
        'findings',
        'report_path',
        'prepared_at',
        'started_at',
        'closed_at',
    ];

    protected $casts = [
        'scope' => 'array',
        'evidence_manifest' => 'array',
        'findings' => 'array',
        'prepared_at' => 'datetime',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
    ];
}
