<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final class EvaluateReleaseGate
{
    /** @var array<string,Rule> */
    private array $rules = [];

    /** @param iterable<Rule> $rules */
    public function __construct(iterable $rules = [])
    {
        foreach ($rules as $rule) {
            $definition = RuleCatalogue::get($rule->id());
            if (isset($this->rules[$definition->id])) {
                throw new \InvalidArgumentException('Duplicate release rule: '.$definition->id);
            }
            $this->rules[$definition->id] = $rule;
        }
    }

    /** @param list<RiskAcceptance> $acceptances */
    public function evaluate(DocumentContext $context, ?RuleConfiguration $configuration = null, array $acceptances = []): GateReport
    {
        $configuration ??= new RuleConfiguration;
        $results = [];
        foreach (RuleCatalogue::all() as $definition) {
            $rule = $this->rules[$definition->id] ?? null;
            if ($rule === null) {
                $results[] = new RuleResult($definition, false);

                continue;
            }
            $checks = $definition->externalOnly && $context->audience === Audience::Internal
                ? [new Subcheck('audience', CheckStatus::NotApplicable, $definition->severity,
                    'This check applies to external progression.', scopeReason: 'The requested action is internal Draft viewing.')]
                : $rule->evaluate($context, $configuration);
            $results[] = new RuleResult($definition, true, $checks);
        }

        return new GateReport($context, $configuration, $results, $acceptances);
    }
}
