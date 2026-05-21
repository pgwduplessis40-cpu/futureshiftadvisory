<?php

declare(strict_types=1);

namespace App\Services\DataQuality;

use App\Models\Client;

final readonly class DataQualityScore
{
    /**
     * @param  array<int, DataQualitySignal>  $signals
     */
    public function __construct(
        public string $level,
        public int $score,
        public array $signals,
    ) {}

    public function label(): string
    {
        return match ($this->level) {
            Client::DATA_QUALITY_HIGH => 'High',
            Client::DATA_QUALITY_MEDIUM => 'Medium',
            Client::DATA_QUALITY_LOW => 'Low',
            default => 'Insufficient',
        };
    }

    public function message(): string
    {
        return match ($this->level) {
            Client::DATA_QUALITY_HIGH => 'Ready for analysis with strong questionnaire and document support.',
            Client::DATA_QUALITY_MEDIUM => 'Ready for basic analysis, with some data quality improvements still useful.',
            default => 'Improve data first: complete the questionnaire and verify supporting documents before analysis runs.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'level' => $this->level,
            'label' => $this->label(),
            'score' => $this->score,
            'message' => $this->message(),
            'components' => array_map(
                static fn (DataQualitySignal $signal): array => $signal->toPayload(),
                $this->signals,
            ),
        ];
    }
}
