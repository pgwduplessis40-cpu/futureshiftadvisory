<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Models\BusinessPlan;
use App\Models\Document;
use App\Models\PlanClaimVersion;
use App\Models\PlanGateRun;
use App\Models\PlanSection;
use App\Services\Entrepreneurs\BusinessPlanExecutiveSummary;

final class SourceCapture
{
    public function capture(BusinessPlan $plan): SourceVersion
    {
        if ($plan->exists) {
            $plan->refresh()->load('sections', 'budgetRunway', 'entrepreneurProfile');
        } else {
            $plan->loadMissing('sections', 'budgetRunway', 'entrepreneurProfile');
        }
        $sections = $plan->sections->sortBy('id')->map(fn (PlanSection $s): array => [
            'id' => (string) $s->getKey(), 'key' => $s->key, 'title' => $s->title, 'body' => $s->body,
            'metadata' => $s->metadata, 'attached_document_ids' => $s->attached_document_ids ?? [],
            'completeness_status' => $s->completeness_status,
        ])->values()->all();
        $inputs = array_values(array_filter($sections, function (array $s): bool {
            $summary = $s['key'] === BusinessPlanExecutiveSummary::SECTION_KEY
                || data_get($s, 'metadata.requirement_key') === BusinessPlanExecutiveSummary::REQUIREMENT_KEY
                || strcasecmp(trim((string) $s['title']), 'Executive summary') === 0;

            return ! ($summary && data_get($s, 'metadata.executive_summary.generated') === true);
        }));
        $documentIds = $plan->sections->flatMap(fn (PlanSection $s): array => (array) $s->attached_document_ids)->unique()->all();
        $evidence = ! $plan->exists || $documentIds === [] ? [] : Document::query()->whereIn('id', $documentIds)->orderBy('id')->get()
            ->map(fn (Document $d): array => $d->only(['id', 'sha256', 'scanner_result', 'expires_at']))->all();
        $claims = ! $plan->exists ? [] : PlanClaimVersion::query()->where('business_plan_id', $plan->getKey())->orderBy('claim_key')->orderBy('revision')->get()
            ->map(fn (PlanClaimVersion $c): array => $c->only(['id', 'claim_key', 'revision', 'state', 'source_hash', 'fact']))->all();
        $budget = $plan->budgetRunway?->only(['id', 'revision', 'status', 'forecast_years', 'expected_runway_months',
            'assumptions', 'launch_costs', 'monthly_fixed_costs', 'future_costs', 'revenue_forecast', 'funding_sources', 'funding_scenarios', 'computed']);
        $requiredHorizon = $plan->exists ? (int) PlanGateRun::query()->where('business_plan_id', $plan->getKey())->max('required_horizon') : 0;
        foreach ($claims as $claim) {
            if (data_get($claim, 'fact.key') === 'scope.required_horizon' && is_numeric(data_get($claim, 'fact.value'))) {
                $requiredHorizon = max($requiredHorizon, (int) data_get($claim, 'fact.value'));
            }
        }
        $identity = $plan->entrepreneurProfile?->only(['id', 'company_name', 'name', 'concept_summary']);
        $source = ['plan_id' => (string) $plan->getKey(), 'identity' => $identity, 'sections' => $sections,
            'budget' => $budget, 'claims' => $claims, 'evidence' => $evidence, 'required_horizon' => $requiredHorizon];

        return new SourceVersion(
            (string) $plan->getKey(), CanonicalJson::hash([$identity, $inputs, $budget, $claims, $evidence]),
            CanonicalJson::hash($sections), $this->dependencies(), $requiredHorizon, now()->toDateString(), CanonicalJson::encode($source),
        );
    }

    /** @return array<string, string> */
    private function dependencies(): array
    {
        // These PHP services contain the calculation, prompt and PDF templates.
        // A deploy changing any of them invalidates prior risk decisions.
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Services/Entrepreneurs'), \FilesystemIterator::SKIP_DOTS));
        $hashes = [];
        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $path = str_replace('\\', '/', $file->getPathname());
                $hashes[substr($path, strlen(str_replace('\\', '/', app_path())))] = hash_file('sha256', $file->getPathname()) ?: '';
            }
        }
        ksort($hashes);

        return ['services' => CanonicalJson::hash($hashes), 'catalogue' => RuleCatalogue::VERSION];
    }
}
