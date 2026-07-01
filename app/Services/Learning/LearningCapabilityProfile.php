<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningUpdate;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class LearningCapabilityProfile
{
    private const CAPABILITY_RULES = [
        'Data' => ['data', 'source', 'quality', 'reference', 'integration', 'document', 'evidence', 'questionnaire', 'benchmark', 'registry', 'attribution'],
        'Finance' => ['financial', 'finance', 'pv', 'fee', 'proposal', 'valuation', 'accounting', 'funding', 'invoice', 'grant', 'cost', 'beneficiary'],
        'Productivity' => ['voice', 'assistant', 'template', 'knowledge', 'notification', 'communication', 'meeting', 'action', 'email', 'workflow aid'],
        'Operations' => ['operations', 'operational', 'systems', 'process', 'sop', 'lifecycle', 'handoff', 'workflow', 'activation'],
        'Financial Planning and Analysis' => ['budget', 'forecast', 'scenario', 'runway', 'cash flow', 'variance', 'model', 'plan', 'trend'],
        'decision-toolkit' => ['decision', 'approve', 'review', 'proposal', 'strategic', 'priority', 'red flag', 'recommendation', 'conversion', 'trade-off'],
        'fact-checker' => ['fact', 'verify', 'verification', 'accuracy', 'document', 'regulatory', 'legislative', 'compliance', 'source attribution', 'website'],
        'skill-creator' => ['skill', 'claude', 'prompt operating', 'template suggestion', 'reusable', 'project memory'],
        'forecasting-time-series-data' => ['forecast', 'time-series', 'trend', 'economic indicator', 'wellbeing', 'valuation history', 'monthly', 'cadence', 'budget', 'runway'],
    ];

    private const SURFACE_RULES = [
        'analysis_modules' => ['analysis', 'module', 'finding', 'red flag'],
        'document_verification' => ['document verification', 'verify_document', 'accuracy_discrepancy', 'document'],
        'entrepreneur_ai' => ['entrepreneur', 'business plan', 'idea validation', 'rating framework'],
        'voice_assistant' => ['voice', 'assistant', 'voice note', 'shortcut'],
        'knowledge_capture' => ['knowledge', 'offboarding', 'draft'],
        'template_suggestions' => ['template', 'template suggestion'],
        'npo_ai' => ['npo', 'governance', 'funder', 'beneficiary'],
        'report_narratives' => ['report', 'narrative', 'accountability'],
        'learning_queue' => ['learning', 'bias', 'calibration', 'approval'],
        'budget_forecast' => ['budget', 'forecast', 'runway', 'cash flow', 'scenario'],
    ];

    public function __construct(private readonly LayerCadenceRegistry $registry) {}

    /**
     * @return array<string, mixed>
     */
    public function forUpdate(LearningUpdate $update): array
    {
        $definition = null;

        if ($update->layer_id !== null) {
            try {
                $definition = $this->registry->definition((int) $update->layer_id);
            } catch (\InvalidArgumentException) {
                $definition = null;
            }
        }

        return $this->profile(
            layerId: $update->layer_id === null ? null : (int) $update->layer_id,
            layerName: is_array($definition) ? (string) ($definition['name'] ?? '') : '',
            command: is_array($definition) ? (string) ($definition['command'] ?? '') : '',
            metadata: is_array($definition) ? (array) ($definition['metadata'] ?? []) : [],
            summary: (string) ($update->summary ?? ''),
            source: $update->source ?? [],
            proposedChange: $update->proposed_change ?? [],
            impactScope: $update->impact_scope ?? [],
            evidence: $update->evidence ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public function forLayerDefinition(array $definition): array
    {
        return $this->profile(
            layerId: (int) ($definition['id'] ?? 0),
            layerName: (string) ($definition['name'] ?? ''),
            command: (string) ($definition['command'] ?? ''),
            metadata: (array) ($definition['metadata'] ?? []),
            summary: (string) ($definition['name'] ?? ''),
            source: [
                'type' => 'learning_layer_registry',
                'layer_id' => $definition['id'] ?? null,
            ],
            proposedChange: [
                'action' => $definition['command'] ?? 'review_learning_layer_signal',
            ],
            impactScope: [
                'surface' => data_get($definition, 'metadata.surface'),
                'module' => data_get($definition, 'metadata.module'),
            ],
            evidence: [
                'feeds' => data_get($definition, 'metadata.feeds', []),
                'guardrail' => data_get($definition, 'metadata.governance_gate'),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $proposedChange
     * @param  array<string, mixed>  $impactScope
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function profile(
        ?int $layerId,
        string $layerName,
        string $command,
        array $metadata,
        string $summary,
        array $source,
        array $proposedChange,
        array $impactScope,
        array $evidence,
    ): array {
        $haystack = $this->haystack($layerId, $layerName, $command, $metadata, $summary, $source, $proposedChange, $impactScope, $evidence);
        $capabilities = $this->normaliseCapabilities([
            ...$this->listFrom($metadata, 'capabilities'),
            ...$this->listFrom($proposedChange, 'capabilities'),
            ...$this->listFrom($impactScope, 'capabilities'),
            ...$this->detected(self::CAPABILITY_RULES, $haystack),
        ]);
        $surfaces = $this->normaliseList([
            ...$this->listFrom($metadata, 'ai_surfaces'),
            ...$this->listFrom($proposedChange, 'ai_surfaces'),
            ...$this->listFrom($impactScope, 'ai_surfaces'),
            ...$this->detected(self::SURFACE_RULES, $haystack),
        ]);

        if ($capabilities === []) {
            $capabilities = ['Data', 'decision-toolkit'];
        }

        if ($surfaces === []) {
            $surfaces = ['learning_queue'];
        }

        return [
            'capabilities' => $capabilities,
            'ai_surfaces' => $surfaces,
            'business_value' => $this->businessValue($capabilities),
            'review_focus' => $this->reviewFocus($capabilities),
            'advice_quality' => $this->adviceQuality($capabilities, $surfaces, $haystack),
            'governance' => [
                'approval_required' => (bool) data_get($proposedChange, 'requires_approval', true),
                'automatic_application' => (bool) data_get($proposedChange, 'automatic_application', false),
                'fact_check_required' => in_array('fact-checker', $capabilities, true),
                'advisor_review_required' => true,
            ],
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $rules
     * @return array<int, string>
     */
    private function detected(array $rules, string $haystack): array
    {
        return collect($rules)
            ->filter(fn (array $needles): bool => collect($needles)->contains(
                fn (string $needle): bool => Str::contains($haystack, Str::lower($needle)),
            ))
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function normaliseCapabilities(array $values): array
    {
        $canonical = collect(array_keys(self::CAPABILITY_RULES))->keyBy(fn (string $value): string => Str::lower($value));

        return collect($values)
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->map(fn (string $value): string => $canonical->get(Str::lower($value), $value))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function normaliseList(array $values): array
    {
        return collect($values)
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function listFrom(array $payload, string $key): array
    {
        $value = Arr::get($payload, $key, []);

        return is_array($value) ? array_values($value) : [$value];
    }

    private function businessValue(array $capabilities): string
    {
        $messages = [
            'Data' => 'Preserve evidence quality, source lineage, uncertainty, and tenant-safe data boundaries.',
            'Finance' => 'Quantify commercial value, financial risk, assumptions, and NZ-specific financial context.',
            'Productivity' => 'Improve advisor speed without bypassing review, audit, client scope, or approval controls.',
            'Operations' => 'Turn process and systems patterns into measurable workflow fixes and automation opportunities.',
            'Financial Planning and Analysis' => 'Separate historicals, assumptions, scenarios, forecasts, and sensitivity before advice changes.',
            'decision-toolkit' => 'Make trade-offs, risks, options, and recommended decisions explicit for human approval.',
            'fact-checker' => 'Verify claims against supplied or official sources and flag unsupported or contradictory evidence.',
            'skill-creator' => 'Keep AI-assistant operating rules concise, reusable, triggerable, and tested.',
            'forecasting-time-series-data' => 'Treat trends and forecasts as uncertain, time-window-dependent signals, not facts.',
        ];

        return collect($capabilities)
            ->map(fn (string $capability): ?string => $messages[$capability] ?? null)
            ->filter()
            ->take(3)
            ->implode(' ');
    }

    /**
     * @param  array<int, string>  $capabilities
     * @param  array<int, string>  $surfaces
     * @return array<string, mixed>
     */
    private function adviceQuality(array $capabilities, array $surfaces, string $haystack): array
    {
        $financialReview = collect([
            'Finance',
            'Financial Planning and Analysis',
            'forecasting-time-series-data',
        ])->intersect($capabilities)->isNotEmpty();
        $valuationReview = in_array('Finance', $capabilities, true)
            && Str::contains($haystack, ['valuation', 'present value', 'discount rate', 'enterprise value', 'funding']);
        $budgetReview = in_array('Financial Planning and Analysis', $capabilities, true)
            || in_array('forecasting-time-series-data', $capabilities, true)
            || in_array('budget_forecast', $surfaces, true);

        $standards = [
            'Evidence must be traceable to supplied, stored, or official sources before client-facing use.',
            'Methodology must be fit for the client context and reviewed before it changes advice.',
            'Facts, assumptions, forecasts, calculations, and recommendations must remain clearly separated.',
            'Reviewer decisions and impact reviews must record what worked, what failed, and any rollback evidence.',
            'Bias, client scope, uncertainty, and unsupported claims must be checked before approval.',
        ];

        if ($financialReview) {
            $standards[] = 'Financial calculations must be validated for formula logic, inputs, units, periods, and materiality.';
        }

        if ($valuationReview) {
            $standards[] = 'Valuation outputs must state the method, assumptions, ranges, sensitivities, and evidence limitations.';
        }

        if ($budgetReview) {
            $standards[] = 'Budgets and forecasts must separate historicals, assumptions, scenarios, timing, and sensitivity.';
        }

        if (in_array('fact-checker', $capabilities, true)) {
            $standards[] = 'Unsupported, stale, or contradictory claims must be flagged before they can support advice.';
        }

        return [
            'learning_outcome_review_required' => true,
            'methodology_review_required' => true,
            'evidence_review_required' => true,
            'bias_review_required' => true,
            'truthfulness_review_required' => true,
            'calculation_validation_required' => $financialReview,
            'valuation_review_required' => $valuationReview,
            'budget_principle_review_required' => $budgetReview,
            'standards' => collect($standards)->unique()->values()->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function reviewFocus(array $capabilities): array
    {
        $focus = [
            'Data' => 'Check source quality, completeness, client scope, and attribution.',
            'Finance' => 'Validate assumptions, calculations, ranges, and commercial materiality.',
            'Productivity' => 'Confirm generated actions remain reviewable, audited, and client-safe.',
            'Operations' => 'Confirm the recommended fix removes a real bottleneck or risk.',
            'Financial Planning and Analysis' => 'Review forecast window, sensitivity, and separation of facts from assumptions.',
            'decision-toolkit' => 'Confirm options, risks, downsides, and recommendation logic are visible.',
            'fact-checker' => 'Check unsupported or stale claims before client-facing use.',
            'skill-creator' => 'Keep the skill concise, valid, scoped, and covered by tests.',
            'forecasting-time-series-data' => 'Review seasonality, outliers, sample size, and uncertainty wording.',
        ];

        return collect($capabilities)
            ->map(fn (string $capability): ?string => $focus[$capability] ?? null)
            ->filter()
            ->take(5)
            ->values()
            ->all();
    }

    private function haystack(mixed ...$values): string
    {
        return Str::lower(json_encode($values, JSON_THROW_ON_ERROR));
    }
}
