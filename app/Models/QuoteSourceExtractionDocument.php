<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuoteSourceExtractionDocument extends Model
{
    use HasUuids;

    protected $guarded = [];

    /**
     * @return BelongsTo<QuoteSourceExtraction, QuoteSourceExtractionDocument>
     */
    public function extraction(): BelongsTo
    {
        return $this->belongsTo(QuoteSourceExtraction::class, 'quote_source_extraction_id');
    }

    /**
     * @return BelongsTo<Document, QuoteSourceExtractionDocument>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<DocumentVerification, QuoteSourceExtractionDocument>
     */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(DocumentVerification::class, 'document_verification_id');
    }
}
