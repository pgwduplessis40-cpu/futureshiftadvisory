<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class SourceVersion implements \JsonSerializable
{
    /** @param array<string,string> $dependencies */
    public function __construct(
        public string $planId,
        public string $inputsHash,
        public string $contentHash,
        public array $dependencies,
        public int $requiredHorizon,
        public string $asOf,
        public string $sourceJson,
    ) {
        if ($requiredHorizon < 0 || ! preg_match('/^[a-f0-9]{64}$/', $inputsHash) || ! preg_match('/^[a-f0-9]{64}$/', $contentHash)) {
            throw new \InvalidArgumentException('Invalid source-version identity.');
        }
        json_decode($sourceJson, true, 512, JSON_THROW_ON_ERROR);
    }

    public function hash(): string
    {
        return CanonicalJson::hash($this);
    }

    /** @return array{plan_id:string,inputs_hash:string,content_hash:string,dependencies:array<string,string>,required_horizon:int,as_of:string,source_json:string} */
    public function jsonSerialize(): array
    {
        return ['plan_id' => $this->planId, 'inputs_hash' => $this->inputsHash, 'content_hash' => $this->contentHash,
            'dependencies' => $this->dependencies, 'required_horizon' => $this->requiredHorizon, 'as_of' => $this->asOf, 'source_json' => $this->sourceJson];
    }
}
