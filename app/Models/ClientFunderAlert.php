<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClientFunderAlert extends Model
{
    use HasUuids;

    public const TYPE_REPORT_DUE_30 = 'report_due_30';

    public const TYPE_REPORT_DUE_7 = 'report_due_7';

    public const TYPE_REPORT_OVERDUE = 'report_overdue';

    public const TYPE_APPLICATION_WINDOW_60 = 'application_window_60';

    public const TYPE_APPLICATION_WINDOW_OPEN = 'application_window_open';

    public const TYPE_GRANT_EXPIRY_60 = 'grant_expiry_60';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    protected $guarded = [];

    protected $casts = [
        'due_on' => 'date',
        'triggered_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Client, ClientFunderAlert>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<ClientFunderRecord, ClientFunderAlert>
     */
    public function clientFunderRecord(): BelongsTo
    {
        return $this->belongsTo(ClientFunderRecord::class);
    }
}
