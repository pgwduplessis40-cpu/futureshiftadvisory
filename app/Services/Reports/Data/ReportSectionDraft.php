<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

use App\Models\AnalysisFinding;

/**
 * Typed persistence payload for a generated report section.
 *
 * @phpstan-type Attribution array{claim: string, source_reference: string}
 * @phpstan-type SectionMetadata array<string, array|bool|float|int|string>
 */
final readonly class ReportSectionDraft
{
    /**
     * @param  list<Attribution>  $attributions
     * @param  SectionMetadata  $metadata
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $body,
        public array $attributions,
        public string $documentSupport,
        public string $documentSupportNote,
        public string $dataQualityNote,
        public array $metadata = [],
    ) {}

    /**
     * @param  SectionMetadata  $metadata
     */
    public static function generated(
        string $key,
        string $title,
        string $body,
        string $sourceReference,
        ?string $dataQualityNote = null,
        array $metadata = [],
    ): self {
        return new self(
            key: $key,
            title: $title,
            body: $body,
            attributions: [[
                'claim' => $title,
                'source_reference' => $sourceReference,
            ]],
            documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
            documentSupportNote: '',
            dataQualityNote: $dataQualityNote ?? '',
            metadata: $metadata,
        );
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     body: string,
     *     lens: null,
     *     attributions: list<Attribution>,
     *     document_support: string,
     *     document_support_note: string,
     *     data_quality_note: string,
     *     metadata: SectionMetadata
     * }
     */
    public function toAttributes(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'body' => $this->body,
            'lens' => null,
            'attributions' => $this->attributions,
            'document_support' => $this->documentSupport,
            'document_support_note' => $this->documentSupportNote,
            'data_quality_note' => $this->dataQualityNote,
            'metadata' => $this->metadata,
        ];
    }
}
