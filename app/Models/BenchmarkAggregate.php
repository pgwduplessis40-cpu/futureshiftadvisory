<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BenchmarkAggregate extends Model
{
    use HasUuids;

    public const DOMAIN_SME = 'sme';

    public const DOMAIN_ENTREPRENEUR = 'entrepreneur';

    protected $guarded = [];

    protected $casts = [
        'distribution' => 'array',
        'cohort_size' => 'integer',
        'suppressed' => 'boolean',
        'generated_at' => 'datetime',
        'privacy_counsel_signed_off_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, BenchmarkAggregate>
     */
    public function privacyCounsel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'privacy_counsel_user_id');
    }
}
