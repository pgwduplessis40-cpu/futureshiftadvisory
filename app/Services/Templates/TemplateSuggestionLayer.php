<?php

declare(strict_types=1);

namespace App\Services\Templates;

use App\Enums\ReportType;
use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\Template;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TemplateSuggestionLayer
{
    public const LAYER_ID = 33;

    private const PROMPT_ID = 'templates.suggest_from_completed_report';

    private const PROMPT_VERSION = '2026-05-26';

    public function __construct(
        private readonly AiClient $ai,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function run(
        int $windowDays = 90,
        int $maxCandidates = 5,
        ?CarbonInterface $windowEnd = null,
    ): LearningLayerRun {
        $windowDays = max(1, $windowDays);
        $maxCandidates = max(1, $maxCandidates);
        $windowEnd ??= now();
        $windowStart = $windowEnd->copy()->subDays($windowDays);

        $this->context->apply('system', []);

        $created = 0;

        foreach ($this->sourceReports($windowStart, $windowEnd, $maxCandidates * 2) as $report) {
            if ($created >= $maxCandidates || $this->candidateExists($report, $windowStart, $windowEnd)) {
                continue;
            }

            $sourceReference = $this->sourceReference($report);
            $response = $this->ai->summarise($this->prompt($report, $sourceReference));

            $created += DB::transaction(function () use ($report, $response, $windowStart, $windowEnd, $sourceReference): int {
                if ($this->candidateExists($report, $windowStart, $windowEnd)) {
                    return 0;
                }

                $template = $this->createDraftTemplate($report, $response, $sourceReference);
                $update = $this->createLearningUpdate($report, $template, $response, $windowStart, $windowEnd, $sourceReference);

                $this->audit->record('template_suggestion.detected', subject: $template, after: [
                    'learning_update_id' => $update->getKey(),
                    'source_reference' => $sourceReference,
                    'status' => Template::STATUS_DRAFT,
                    'automatic_application' => false,
                ]);

                return 1;
            });
        }

        $run = LearningLayerRun::query()->create([
            'layer_id' => self::LAYER_ID,
            'ran_at' => now(),
            'candidates_created' => $created,
            'window' => [
                'window_start' => $windowStart->toIso8601String(),
                'window_end' => $windowEnd->toIso8601String(),
                'window_days' => $windowDays,
                'max_candidates' => $maxCandidates,
                'governed_candidates_only' => true,
                'automatic_application' => false,
            ],
            'status' => LearningLayerRun::STATUS_COMPLETED,
        ]);

        $this->audit->record('template_suggestion_layer.ran', subject: $run, after: [
            'layer_id' => self::LAYER_ID,
            'candidates_created' => $created,
            'window_start' => $windowStart->toIso8601String(),
            'window_end' => $windowEnd->toIso8601String(),
        ]);

        return $run;
    }

    /**
     * @return Collection<int, Report>
     */
    private function sourceReports(CarbonInterface $windowStart, CarbonInterface $windowEnd, int $limit): Collection
    {
        return Report::query()
            ->with('sections')
            ->whereBetween('generated_at', [$windowStart, $windowEnd])
            ->where(function ($query): void {
                $query
                    ->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'pending_review');
            })
            ->latest('generated_at')
            ->limit(max(1, $limit))
            ->get();
    }

    private function candidateExists(Report $report, CarbonInterface $windowStart, CarbonInterface $windowEnd): bool
    {
        $sourceReference = $this->sourceReference($report);
        $signalKey = $this->signalKey($report, $windowStart, $windowEnd);

        return Template::query()
            ->where('source_reference', $sourceReference)
            ->exists()
            || LearningUpdate::query()
                ->where('layer_id', self::LAYER_ID)
                ->where('source->signal_key', $signalKey)
                ->exists();
    }

    private function createDraftTemplate(Report $report, AiResponse $response, string $sourceReference): Template
    {
        /** @var Template $template */
        $template = Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => $this->templateTitle($report),
            'body' => $this->templateBody($report, $response),
            'structure' => [
                'source_kind' => 'completed_report',
                'report_type' => $this->reportTypeValue($report),
                'sections' => $this->sectionBlueprint($report),
                'ai' => [
                    'model' => $response->model,
                    'prompt_id' => self::PROMPT_ID,
                    'prompt_version' => $response->promptVersion,
                    'prompt_hash' => $response->promptHash,
                    'uncertainty' => $response->uncertainty->value,
                ],
            ],
            'source_reference' => $sourceReference,
            'status' => Template::STATUS_DRAFT,
            'version' => 1,
            'created_by_user_id' => null,
            'learning_update_implementation_id' => null,
        ]);

        return $template;
    }

    private function createLearningUpdate(
        Report $report,
        Template $template,
        AiResponse $response,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
        string $sourceReference,
    ): LearningUpdate {
        /** @var LearningUpdate $update */
        $update = LearningUpdate::query()->create([
            'layer_id' => self::LAYER_ID,
            'source' => [
                'type' => 'template_suggestion_layer',
                'signal_key' => $this->signalKey($report, $windowStart, $windowEnd),
                'source_reference' => $sourceReference,
                'report_type' => $this->reportTypeValue($report),
                'section_count' => $report->sections->count(),
                'window_start' => $windowStart->toIso8601String(),
                'window_end' => $windowEnd->toIso8601String(),
            ],
            'summary' => sprintf('Review dormant template suggestion "%s" for activation.', $template->title),
            'proposed_change' => [
                'action' => 'activate_template',
                'template_id' => $template->getKey(),
                'category' => $template->category,
                'automatic_application' => false,
                'requires_approval' => true,
            ],
            'impact_scope' => [
                'surface' => 'template_library',
                'tenant_scope' => 'practice',
                'template_status' => Template::STATUS_DRAFT,
            ],
            'clients_affected' => 0,
            'magnitude' => 'low',
            'confidence' => $response->uncertainty->value === 'high' ? 0.55 : 0.7,
            'evidence' => [
                'template_id' => $template->getKey(),
                'source_reference' => $sourceReference,
                'attributions' => [
                    [
                        'claim' => 'Template suggestion was derived from anonymised completed report structure.',
                        'source_reference' => $sourceReference,
                    ],
                    ...$response->attributions,
                ],
                'model' => $response->model,
                'prompt_id' => self::PROMPT_ID,
                'prompt_version' => $response->promptVersion,
                'prompt_hash' => $response->promptHash,
                'uncertainty' => $response->uncertainty->value,
                'metadata' => $response->metadata,
                'guardrail' => 'draft_template_only_no_runtime_use',
                'client_pii_excluded' => true,
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        $this->audit->record('learning_update.detected', subject: $update, after: [
            'layer_id' => self::LAYER_ID,
            'template_id' => $template->getKey(),
            'source_reference' => $sourceReference,
            'automatic_application' => false,
        ]);

        return $update;
    }

    private function prompt(Report $report, string $sourceReference): PromptEnvelope
    {
        return new PromptEnvelope(
            id: self::PROMPT_ID,
            version: self::PROMPT_VERSION,
            task: 'Suggest a reusable internal advisory template from completed report structure.',
            body: 'Use only anonymised structure, report type, section count, and section metadata. Do not include client names, NZBNs, contact details, report titles, or section body text.',
            input: [
                'source_reference' => $sourceReference,
                'report_type' => $this->reportTypeValue($report),
                'section_count' => $report->sections->count(),
                'sections' => $report->sections
                    ->sortBy('position')
                    ->map(fn (ReportSection $section): array => [
                        'position' => $section->position,
                        'lens' => $section->lens,
                        'document_support' => $section->document_support,
                        'word_count' => str_word_count($section->body),
                    ])
                    ->values()
                    ->all(),
            ],
            dataQualitySummary: [
                'level' => 'completed_report_structure_only',
                'message' => 'Prompt excludes client PII and report prose; output remains a governed draft until approved and implemented.',
            ],
            sourceReferences: [$sourceReference],
        );
    }

    private function templateTitle(Report $report): string
    {
        $label = $report->type instanceof ReportType
            ? $report->type->label()
            : Str::headline((string) $report->type);

        return $label.' reusable template';
    }

    private function templateBody(Report $report, AiResponse $response): string
    {
        $sectionCount = max(1, $report->sections->count());
        $plural = $sectionCount === 1 ? 'section' : 'sections';

        return implode("\n\n", [
            'Purpose',
            sprintf('Use this practice-wide template as a starting point for a %s deliverable with %d %s.', Str::lower($this->reportTypeLabel($report)), $sectionCount, $plural),
            'Drafting guidance',
            trim($response->text),
            'Guardrail',
            'Adapt to the specific engagement before use. This template was created as a dormant draft and is not usable until approved and implemented.',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sectionBlueprint(Report $report): array
    {
        return $report->sections
            ->sortBy('position')
            ->values()
            ->map(fn (ReportSection $section, int $index): array => [
                'position' => $section->position ?: $index + 1,
                'heading' => 'Section '.($section->position ?: $index + 1),
                'lens' => $section->lens,
                'purpose' => 'Adapt this section to the engagement context with current evidence.',
            ])
            ->all();
    }

    private function reportTypeValue(Report $report): string
    {
        return $report->type instanceof ReportType
            ? $report->type->value
            : (string) $report->type;
    }

    private function reportTypeLabel(Report $report): string
    {
        return $report->type instanceof ReportType
            ? $report->type->label()
            : Str::headline((string) $report->type);
    }

    private function sourceReference(Report $report): string
    {
        return 'report:'.$report->getKey();
    }

    private function signalKey(Report $report, CarbonInterface $windowStart, CarbonInterface $windowEnd): string
    {
        return hash('sha256', implode('|', [
            $this->sourceReference($report),
            $windowStart->toDateString(),
            $windowEnd->toDateString(),
        ]));
    }
}
