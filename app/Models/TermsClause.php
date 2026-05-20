<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TermsClause extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'clause_number' => 'integer',
        'material' => 'boolean',
    ];

    /**
     * @return BelongsTo<TermsVersion, TermsClause>
     */
    public function termsVersion(): BelongsTo
    {
        return $this->belongsTo(TermsVersion::class);
    }
}
