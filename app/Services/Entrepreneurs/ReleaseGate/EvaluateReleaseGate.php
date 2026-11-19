<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Support\Methodology\ProvidesMethodology;

final class EvaluateReleaseGate implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.release_gate'];
    }

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
            // A withdrawn rule is not deferred work. Registering one would put
            // a verdict back on a document from a rule the catalogue says
            // should no longer produce one, so it is refused here rather than
            // quietly ignored.
            if ($definition->withdrawn !== null) {
                throw new \InvalidArgumentException('Withdrawn release rule cannot be registered: '.$definition->id);
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
            // A withdrawn rule produces no result at all. Reporting it as
            // deferred would say the gate still intends to check this, which
            // is the opposite of what withdrawing it decided.
            if ($definition->withdrawn !== null) {
                continue;
            }
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
