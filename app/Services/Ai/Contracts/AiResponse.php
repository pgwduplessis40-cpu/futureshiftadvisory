<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use InvalidArgumentException;

final readonly class AiResponse
{
    /**
     * @param  array<int, array{claim:string, source_reference:string}>  $attributions
     * @param  array<int, array<string, mixed>>  $biasSignals
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $text,
        public array $attributions,
        public Uncertainty $uncertainty,
        public array $biasSignals,
        public string $model,
        public string $promptVersion,
        public string $promptHash,
        public int $tokensIn,
        public int $tokensOut,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromStructuredPayload(
        array $payload,
        string $model,
        string $promptVersion,
        string $promptHash,
        int $tokensIn = 0,
        int $tokensOut = 0,
    ): self {
        if (! isset($payload['text']) || ! is_string($payload['text'])) {
            throw new InvalidArgumentException('AI response payload missing string field: text');
        }

        $uncertainty = Uncertainty::tryFrom((string) ($payload['uncertainty'] ?? 'high'));
        if ($uncertainty === null) {
            throw new InvalidArgumentException('AI response payload has an invalid uncertainty value.');
        }

        return new self(
            text: $payload['text'],
            attributions: self::normaliseAttributions($payload['attributions'] ?? []),
            uncertainty: $uncertainty,
            biasSignals: self::normaliseList($payload['bias_signals'] ?? []),
            model: $model,
            promptVersion: $promptVersion,
            promptHash: $promptHash,
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            metadata: self::normaliseMap($payload['metadata'] ?? []),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $biasSignals
     */
    public function withBiasSignals(array $biasSignals): self
    {
        return new self(
            text: $this->text,
            attributions: $this->attributions,
            uncertainty: $this->uncertainty,
            biasSignals: $biasSignals,
            model: $this->model,
            promptVersion: $this->promptVersion,
            promptHash: $this->promptHash,
            tokensIn: $this->tokensIn,
            tokensOut: $this->tokensOut,
            metadata: $this->metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'attributions' => $this->attributions,
            'uncertainty' => $this->uncertainty->value,
            'bias_signals' => $this->biasSignals,
            'model' => $this->model,
            'prompt_version' => $this->promptVersion,
            'prompt_hash' => $this->promptHash,
            'tokens_in' => $this->tokensIn,
            'tokens_out' => $this->tokensOut,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private static function normaliseAttributions(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $out[] = [
                'claim' => (string) ($item['claim'] ?? ''),
                'source_reference' => (string) ($item['source_reference'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normaliseList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function normaliseMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return $value;
    }
}
