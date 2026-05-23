<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PostAcquisitionMigration extends Model
{
    use HasUuids;

    public const STATUS_CREATED = 'created';

    protected $guarded = [];

    protected $casts = [
        'migrated_document_ids' => 'array',
        'dd_pv_baseline' => 'float',
        'metadata' => 'array',
        'migrated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<DdEngagement, PostAcquisitionMigration>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(DdEngagement::class, 'dd_engagement_id');
    }

    /**
     * @return BelongsTo<Client, PostAcquisitionMigration>
     */
    public function buyerClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'buyer_client_id');
    }

    /**
     * @return BelongsTo<Client, PostAcquisitionMigration>
     */
    public function advisoryClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'advisory_client_id');
    }

    /**
     * @return BelongsTo<BusinessPlan, PostAcquisitionMigration>
     */
    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }

    /**
     * @return BelongsTo<Report, PostAcquisitionMigration>
     */
    public function ddReport(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'dd_report_id');
    }

    /**
     * @return BelongsTo<QuestionnaireResponse, PostAcquisitionMigration>
     */
    public function gapQuestionnaireResponse(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireResponse::class, 'gap_questionnaire_response_id');
    }

    /**
     * @return BelongsTo<Proposal, PostAcquisitionMigration>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
