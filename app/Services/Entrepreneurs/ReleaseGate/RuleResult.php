<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class RuleResult implements \JsonSerializable
{
    public ?CheckStatus $status;

    /** @var list<Subcheck> */
    public array $subchecks;

    /** @param list<Subcheck> $subchecks */
    public function __construct(public RuleDefinition $definition, public bool $enabled, array $subchecks = [])
    {
        if (! $enabled && $subchecks !== []) {
            throw new \InvalidArgumentException('A deferred rule cannot have evaluation results.');
        }
        $this->subchecks = $enabled && $subchecks === []
            ? [Subcheck::missing('evaluation', 'the required rule assertions', $definition->severity)] : $subchecks;
        $ids = array_column($this->subchecks, 'id');
        if (count($ids) !== count(array_unique($ids))) {
            throw new \InvalidArgumentException('Subcheck identifiers must be unique within a rule.');
        }
        $unresolved = array_values(array_filter($this->subchecks, fn (Subcheck $s): bool => $s->status->unresolved()));
        if ($unresolved !== []) {
            $highest = max(array_map(fn (Subcheck $s): int => $s->severity->rank(), $unresolved));
            $states = array_map(fn (Subcheck $s): CheckStatus => $s->status,
                array_filter($unresolved, fn (Subcheck $s): bool => $s->severity->rank() === $highest));
            // A reserve-policy Review cannot obscure a missing funding-source Block.
            $this->status = in_array(CheckStatus::Fail, $states, true) ? CheckStatus::Fail : CheckStatus::CannotVerify;
        } else {
            $states = array_map(fn (Subcheck $s): CheckStatus => $s->status, $this->subchecks);
            $this->status = ! $enabled ? null : (in_array(CheckStatus::Pass, $states, true) ? CheckStatus::Pass : CheckStatus::NotApplicable);
        }
    }

    /** @return array{rule:RuleDefinition,enabled:bool,result:string|null,subchecks:list<Subcheck>} */
    public function jsonSerialize(): array
    {
        return ['rule' => $this->definition, 'enabled' => $this->enabled, 'result' => $this->status?->value, 'subchecks' => $this->subchecks];
    }
}
