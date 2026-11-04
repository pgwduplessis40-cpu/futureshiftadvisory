<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class RuleDefinition implements \JsonSerializable
{
    /** @param list<string> $inputs */
    public function __construct(
        public string $id,
        public string $name,
        public string $category,
        public array $inputs,
        public string $condition,
        public Severity $severity,
        public int $phase,
        public bool $externalOnly = false,
    ) {}

    public function acceptsElevatedRisk(): bool
    {
        return in_array($this->id, ['PM-07', 'PM-08', 'MI-05', 'SS-02', 'EV-03', 'EV-04'], true);
    }

    /** @return array{id:string,name:string,category:string,inputs:list<string>,condition:string,severity:string,minimum_severity:string,override_policy:string,applies_to:string,phase:int,applicability:string,message:string,review_resolution:string} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'category' => $this->category, 'inputs' => $this->inputs,
            'condition' => $this->condition, 'severity' => $this->severity->value, 'minimum_severity' => $this->severity->value,
            'override_policy' => $this->acceptsElevatedRisk() ? 'principal_advisor' : 'never',
            'applies_to' => $this->externalOnly ? 'external' : 'both', 'phase' => $this->phase,
            'applicability' => $this->externalOnly ? 'External progression; internal review copies are out of scope with a recorded reason.' : 'Evaluate applicable claims and model scope; unknown inputs are not proof of non-applicability.',
            'message' => $this->condition, 'review_resolution' => 'Principal Advisor may accept an original Review with a recorded reason; original integrity Blocks cannot be waived.'];
    }
}
