<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class GateReport implements \JsonSerializable
{
    /**
     * @param  list<RuleResult>  $results
     * @param  list<RiskAcceptance>  $acceptances
     */
    public function __construct(
        public DocumentContext $context,
        public RuleConfiguration $configuration,
        public array $results,
        public array $acceptances = [],
    ) {}

    /** @return list<string> */
    public function deferred(): array
    {
        return array_values(array_map(fn (RuleResult $r): string => $r->definition->id, array_filter($this->results, fn (RuleResult $r): bool => ! $r->enabled)));
    }

    /** @return list<array{rule_id:string,subcheck:Subcheck,severity:string,accepted:bool}> */
    public function findings(): array
    {
        $findings = [];
        foreach ($this->results as $result) {
            foreach ($result->subchecks as $check) {
                if (! $check->status->unresolved()) {
                    continue;
                }
                $accepted = false;
                foreach ($this->acceptances as $decision) {
                    $accepted = $accepted || $decision->matches($result->definition, $check, $this->dependencyHash($result->definition), $this->configuration);
                }
                $findings[] = ['rule_id' => $result->definition->id, 'subcheck' => $check,
                    'severity' => $this->configuration->severity($result->definition, $check)->value, 'accepted' => $accepted];
            }
        }

        return $findings;
    }

    public function dependencyHash(RuleDefinition $rule): string
    {
        // Financial decisions survive derived-summary writes. Rendered-content
        // checks additionally bind to the exact candidate content.
        return CanonicalJson::hash([$this->context->source->planId, $this->context->source->inputsHash,
            $this->context->source->requiredHorizon, $this->context->source->asOf,
            $this->context->source->dependencies,
            str_starts_with($rule->id, 'DH-') ? $this->context->hash() : 'financial-inputs']);
    }

    public function checksResolved(): bool
    {
        foreach ($this->findings() as $finding) {
            if (! $finding['accepted'] && $finding['severity'] !== Severity::Info->value) {
                return false;
            }
        }

        return true;
    }

    /** @return array{source_hash:string,context_hash:string,configuration_hash:string,document:string,audience:string,status:string,checks_resolved:bool,can_certify:bool,deferred:list<string>,results:list<RuleResult>,findings:list<array{rule_id:string,subcheck:Subcheck,severity:string,accepted:bool}>} */
    public function jsonSerialize(): array
    {
        return ['source_hash' => $this->context->source->hash(), 'context_hash' => $this->context->hash(),
            'configuration_hash' => $this->configuration->hash(), 'document' => $this->context->document,
            'audience' => $this->context->audience->value, 'status' => 'draft', 'checks_resolved' => $this->checksResolved(),
            // Only a later exact-artifact release service may certify; this
            // evaluator cannot certify even if all enabled checks pass.
            'can_certify' => false, 'deferred' => $this->deferred(), 'results' => $this->results, 'findings' => $this->findings()];
    }
}
