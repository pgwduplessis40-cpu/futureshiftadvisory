<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SurveyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Survey extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'status' => SurveyStatus::class,
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * @return HasMany<SurveyQuestion>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('order');
    }

    /**
     * @return HasMany<SurveyAssignment>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(SurveyAssignment::class);
    }

    /**
     * @return HasMany<SurveyResponse>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /**
     * @param  Builder<Survey>  $query
     * @return Builder<Survey>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', SurveyStatus::Published->value);
    }

    public function isPublished(): bool
    {
        return $this->status === SurveyStatus::Published;
    }
}
