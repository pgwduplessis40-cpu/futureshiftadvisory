<?php

declare(strict_types=1);

namespace App\Services\Ai\Prompts;

use App\Services\Ai\Contracts\PromptEnvelope;
use InvalidArgumentException;

final class PromptRegistry
{
    /**
     * @var array<string, array{version:string, body:string, task:string}>
     */
    private array $prompts = [];

    public function __construct()
    {
        $this->register(
            id: 'summarise.smoke',
            version: '2026-05-wo04',
            body: 'Summarise the supplied material without adding facts that are not in the input.',
            task: 'summarise',
        );

        $this->register(
            id: 'document.verify',
            version: '2026-05-wo04',
            body: 'Compare the supplied claim with the supplied document evidence and return only the structured schema.',
            task: 'verify_document',
        );
    }

    public function register(string $id, string $version, string $body, string $task = 'analyse'): void
    {
        $this->prompts[$id] = [
            'version' => $version,
            'body' => $body,
            'task' => $task,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $dataQualitySummary
     * @param  array<int, string>  $sourceReferences
     */
    public function envelope(
        string $id,
        array $input = [],
        array $dataQualitySummary = [],
        array $sourceReferences = [],
    ): PromptEnvelope {
        if (! isset($this->prompts[$id])) {
            throw new InvalidArgumentException("Prompt [{$id}] is not registered.");
        }

        $prompt = $this->prompts[$id];

        return new PromptEnvelope(
            id: $id,
            version: $prompt['version'],
            task: $prompt['task'],
            body: $prompt['body'],
            input: $input,
            dataQualitySummary: $dataQualitySummary,
            sourceReferences: $sourceReferences,
        );
    }

    public function promptHash(string $id): string
    {
        return $this->envelope($id)->hash();
    }
}
