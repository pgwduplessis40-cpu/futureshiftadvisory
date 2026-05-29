<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DdDataRoomItem extends Model
{
    use HasUuids;

    public const SOURCE_ADVISOR_UPLOAD = 'advisor_upload';

    public const SOURCE_CLIENT_UPLOAD = 'client_upload';

    public const SOURCE_GUEST_UPLOAD = 'guest_upload';

    public const ARTIFACT_TYPE = 'dd_artifact';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Client, DdDataRoomItem>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<DdEngagement, DdDataRoomItem>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(DdEngagement::class, 'dd_engagement_id');
    }

    /**
     * @return BelongsTo<Document, DdDataRoomItem>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<DdGuestLink, DdDataRoomItem>
     */
    public function guestLink(): BelongsTo
    {
        return $this->belongsTo(DdGuestLink::class, 'dd_guest_link_id');
    }
}
