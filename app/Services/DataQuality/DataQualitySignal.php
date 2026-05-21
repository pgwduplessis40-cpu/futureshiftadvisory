<?php

declare(strict_types=1);

namespace App\Services\DataQuality;

final readonly class DataQualitySignal
{
    public function __construct(
        public string $key,
        public string $label,
        public int $score,
        public int $weight,
        public string $summary,
        public string $detail,
    ) {}

    public function weightedScore(): float
    {
        return $this->score * ($this->weight / 100);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'score' => $this->score,
            'weight' => $this->weight,
            'summary' => $this->summary,
            'detail' => $this->detail,
        ];
    }
}
