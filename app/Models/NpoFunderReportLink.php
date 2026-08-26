<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NpoFunderReportLink extends Model
{
    use HasUuids;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_REVOKED = 'revoked';

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'npo_engagement_id',
        'report_id',
        'client_funder_record_id',
        'guest_email',
        'status',
        'token_hash',
        'requested_by_user_id',
        'approved_by_user_id',
        'declined_by_user_id',
        'revoked_by_user_id',
        'approved_at',
        'declined_at',
        'decline_reason',
        'expires_at',
        'revoked_at',
        'last_used_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'declined_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && is_string($this->token_hash)
            && $this->token_hash !== ''
            && $this->revoked_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<Client, NpoFunderReportLink>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<NpoEngagement, NpoFunderReportLink>
     */
    public function npoEngagement(): BelongsTo
    {
        return $this->belongsTo(NpoEngagement::class);
    }

    /**
     * @return BelongsTo<Report, NpoFunderReportLink>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * @return BelongsTo<ClientFunderRecord, NpoFunderReportLink>
     */
    public function clientFunderRecord(): BelongsTo
    {
        return $this->belongsTo(ClientFunderRecord::class);
    }

    /**
     * @return HasMany<NpoFunderReportSession>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(NpoFunderReportSession::class);
    }
}
