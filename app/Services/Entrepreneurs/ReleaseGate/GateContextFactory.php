<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Models\BusinessPlan;

final readonly class GateContextFactory
{
    public function __construct(private SourceCapture $capture, private BudgetFacts $budget, private PlanFacts $plan,
        private ForecastModelFacts $forecast = new ForecastModelFacts) {}

    public function current(BusinessPlan $plan, string $document, Audience $audience = Audience::External): DocumentContext
    {
        return $this->fromSource($this->capture->capture($plan), $document, $audience);
    }

    public function fromSource(SourceVersion $version, string $document, Audience $audience = Audience::External): DocumentContext
    {
        $source = new SourceRecords($version);
        // The version-2 producer runs last and emits nothing unless a
        // reconstruction has been selected. It withholds the keys the enabled
        // rules already read, so a selected model adds evidence beside the
        // stored budget without displacing it.
        $facts = [...$this->budget->capture($source), ...$this->plan->capture($source), ...$this->forecast->capture($source)];
        $required = $version->requiredHorizon;
        foreach ($facts as $fact) {
            if ($fact->key === 'scope.required_horizon' && $fact->number() !== null) {
                $required = max($required, $fact->number()->toInt());
            }
        }
        $records = json_decode($version->sourceJson, true, 512, JSON_THROW_ON_ERROR);
        $records['required_horizon'] = $required;
        $version = new SourceVersion($version->planId, $version->inputsHash, $version->contentHash, $version->dependencies, $required, $version->asOf, CanonicalJson::encode($records));

        // Saved narrative is a pre-render candidate. Actual renderer output must
        // be supplied before a document-content rule can achieve a full Pass.
        return new DocumentContext($version, new FactSet($facts), $document, $audience, $document === 'plan' ? $this->plan->blocks($source) : [], false);
    }
}
