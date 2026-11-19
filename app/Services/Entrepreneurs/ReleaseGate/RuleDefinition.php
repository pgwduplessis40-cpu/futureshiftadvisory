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
        /**
         * Why this rule was withdrawn, or null while it stands.
         *
         * A withdrawn rule keeps its catalogue entry and its number. The
         * specification is a numbered document and a rule that simply vanished
         * would read as a gap someone forgot to fill, which invites rebuilding
         * it on the premise that was wrong in the first place. It is not
         * evaluated, is not deferred, and cannot be registered.
         */
        public ?string $withdrawn = null,
    ) {}

    public function acceptsElevatedRisk(): bool
    {
        return in_array($this->id, ['PM-07', 'MI-05', 'SS-02', 'EV-03', 'EV-04'], true);
    }

    /** @return array{id:string,name:string,category:string,inputs:list<string>,condition:string,severity:string,minimum_severity:string,override_policy:string,applies_to:string,phase:int,applicability:string,message:string,review_resolution:string,withdrawn:string|null} */
    public function jsonSerialize(): array
    {
        return ['withdrawn' => $this->withdrawn,
            'id' => $this->id, 'name' => $this->name, 'category' => $this->category, 'inputs' => $this->inputs,
            'condition' => $this->condition, 'severity' => $this->severity->value, 'minimum_severity' => $this->severity->value,
            'override_policy' => $this->acceptsElevatedRisk() ? 'principal_advisor' : 'never',
            'applies_to' => $this->externalOnly ? 'external' : 'both', 'phase' => $this->phase,
            'applicability' => $this->externalOnly ? 'External progression; internal review copies are out of scope with a recorded reason.' : 'Evaluate applicable claims and model scope; unknown inputs are not proof of non-applicability.',
            'message' => $this->condition, 'review_resolution' => 'Principal Advisor may accept an original Review with a recorded reason; original integrity Blocks cannot be waived.'];
    }
}
