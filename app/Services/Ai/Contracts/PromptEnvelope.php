<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Prompts\IntegrityPreamble;

final readonly class PromptEnvelope
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $dataQualitySummary
     * @param  array<int, string>  $sourceReferences
     */
    public function __construct(
        public string $id,
        public string $version,
        public string $task,
        public string $body,
        public array $input = [],
        public array $dataQualitySummary = [],
        public array $sourceReferences = [],
        public string $integrityPreamble = IntegrityPreamble::TEXT,
        public string $integrityPreambleVersion = IntegrityPreamble::VERSION,
    ) {}

    public function hash(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'task' => $this->task,
            'body' => $this->body,
            'input' => $this->input,
            'data_quality_summary' => $this->dataQualitySummary,
            'source_references' => $this->sourceReferences,
            'integrity_preamble' => $this->integrityPreamble,
            'integrity_preamble_version' => $this->integrityPreambleVersion,
            'response_schema' => [
                'text' => 'string',
                'attributions' => [
                    ['claim' => 'string', 'source_reference' => 'string'],
                ],
                'uncertainty' => ['high', 'medium', 'low', 'none'],
                'metadata' => [
                    'findings' => [
                        [
                            'lens' => 'descriptive|diagnostic|predictive|prescriptive',
                            'severity' => 'info|low|medium|high|critical',
                            'title' => 'string',
                            'body' => 'string',
                            'attributions' => [
                                ['claim' => 'string', 'source_reference' => 'string'],
                            ],
                            'uncertainty' => 'high|medium|low|none',
                        ],
                    ],
                ],
            ],
        ];
    }
}
