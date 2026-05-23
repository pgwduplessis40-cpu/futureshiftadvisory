<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Testimonial extends Model
{
    use HasUuids;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_CONSENTED = 'consented';

    public const STATUS_DECLINED = 'declined';

    public const DISPLAY_NAMED = 'named';

    public const DISPLAY_ANONYMOUS = 'anonymous';

    public const SOURCE_NPS = 'nps';

    protected $guarded = [];

    protected $casts = [
        'source_score' => 'integer',
        'marketing_consent' => 'boolean',
        'requested_at' => 'datetime',
        'consented_at' => 'datetime',
        'declined_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
