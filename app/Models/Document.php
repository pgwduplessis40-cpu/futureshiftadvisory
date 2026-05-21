<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Document extends Model
{
    use HasUuids;

    public const CATEGORY_FINANCIAL_STATEMENT = 'financial_statement';

    public const CATEGORY_CONTRACT = 'contract';

    public const CATEGORY_INSURANCE_CERTIFICATE = 'insurance_certificate';

    public const CATEGORY_HR_RECORD = 'hr_record';

    public const CATEGORY_COMPLIANCE_DOC = 'compliance_doc';

    public const CATEGORY_PLAN_ATTACHMENT = 'plan_attachment';

    public const CATEGORY_DD_ARTIFACT = 'dd_artifact';

    public const CATEGORY_OTHER = 'other';

    public const SCANNER_PENDING = 'pending';

    public const SCANNER_CLEAN = 'clean';

    public const SCANNER_INFECTED = 'infected';

    public const SCANNER_ERROR = 'error';

    protected $guarded = [];

    protected $casts = [
        'byte_size' => 'integer',
        'scanner_payload' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeVisibleToClients(Builder $query): Builder
    {
        return $query->where('scanner_result', self::SCANNER_CLEAN);
    }

    public function isVisibleToClients(): bool
    {
        return $this->scanner_result === self::SCANNER_CLEAN;
    }

    /**
     * @return BelongsTo<Client, Document>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<EntrepreneurProfile, Document>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<User, Document>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * @return HasMany<DocumentVerification>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(DocumentVerification::class);
    }
}
