<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class RiskAcceptance implements \JsonSerializable
{
    public function __construct(
        public string $ruleId,
        public string $subcheckId,
        public string $findingHash,
        public string $dependencyHash,
        public string $configurationHash,
        public int $actorId,
        public string $reason,
        public string $acceptedAt,
    ) {
        if ($actorId <= 0 || mb_strlen(trim($reason)) < 15) {
            throw new \InvalidArgumentException('A risk decision requires a recorded Principal Advisor and a meaningful reason.');
        }
    }

    public function matches(RuleDefinition $rule, Subcheck $check, string $dependencyHash, RuleConfiguration $config): bool
    {
        // A decision is incapable of accepting an original integrity Block.
        return $check->severity === Severity::Review
            && ($config->severity($rule, $check) !== Severity::Block || $rule->acceptsElevatedRisk())
            && $rule->id === $this->ruleId && $check->id === $this->subcheckId
            && hash_equals($this->findingHash, $check->hash()) && hash_equals($this->dependencyHash, $dependencyHash)
            && hash_equals($this->configurationHash, $config->hash());
    }

    /** @return array{rule_id:string,subcheck_id:string,finding_hash:string,dependency_hash:string,configuration_hash:string,actor_id:int,reason:string,accepted_at:string} */
    public function jsonSerialize(): array
    {
        return ['rule_id' => $this->ruleId, 'subcheck_id' => $this->subcheckId, 'finding_hash' => $this->findingHash,
            'dependency_hash' => $this->dependencyHash, 'configuration_hash' => $this->configurationHash,
            'actor_id' => $this->actorId, 'reason' => $this->reason, 'accepted_at' => $this->acceptedAt];
    }
}
