<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PanelAgreement extends Model
{
    use HasUuids;

    public const STATUS_PENDING_SIGNATURE = 'pending_signature';

    public const STATUS_SIGNED = 'signed';

    protected $guarded = [];

    protected $casts = [
        'terms' => 'array',
        'pdf_envelope_meta' => 'array',
        'pdf_byte_size' => 'integer',
        'generated_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PanelMember, PanelAgreement>
     */
    public function panelMember(): BelongsTo
    {
        return $this->belongsTo(PanelMember::class);
    }

    /**
     * @return BelongsTo<User, PanelAgreement>
     */
    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }
}
