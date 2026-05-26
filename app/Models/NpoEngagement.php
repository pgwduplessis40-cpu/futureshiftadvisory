<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NpoConversionStatus;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NpoEngagement extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'sub_type' => NpoEngagementSubType::class,
        'legal_structure' => NpoLegalStructure::class,
        'isa_2022_reregistered' => 'boolean',
        'conversion_status' => NpoConversionStatus::class,
        'report_delivered_at' => 'datetime',
        'reengagement_due_at' => 'date',
    ];

    /**
     * @return BelongsTo<Client, NpoEngagement>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<NpoEngagement, NpoEngagement>
     */
    public function convertedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'converted_from_npo_engagement_id');
    }

    /**
     * @return HasMany<NpoEngagement>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(self::class, 'converted_from_npo_engagement_id');
    }

    /**
     * @return BelongsTo<User, NpoEngagement>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, NpoEngagement>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return HasMany<QuestionnaireResponse>
     */
    public function questionnaireResponses(): HasMany
    {
        return $this->hasMany(QuestionnaireResponse::class);
    }

    /**
     * @return HasMany<Report>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * @return HasMany<FeeCalculation>
     */
    public function feeCalculations(): HasMany
    {
        return $this->hasMany(FeeCalculation::class);
    }

    /**
     * @return HasMany<Proposal>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /**
     * @return HasMany<NpoComplianceAlert>
     */
    public function complianceAlerts(): HasMany
    {
        return $this->hasMany(NpoComplianceAlert::class);
    }
}
