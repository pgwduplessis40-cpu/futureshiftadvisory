<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\LearningUpdate;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\Pv\RiskCostPv;

final class RegulatoryImpactAssessor
{
    public function __construct(
        private readonly DataQualityScorer $dataQuality,
        private readonly RiskCostPv $riskPv,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function assess(Client $client, LearningUpdate $change, array $options = []): AnalysisRun
    {
        $score = $this->dataQuality->score($client);
        $impact = max(1000.0, (float) ($options['financial_impact'] ?? data_get($change->proposed_change, 'estimated_financial_impact', 25000)));
        $probability = max(0.05, min(1.0, (float) ($options['probability'] ?? 0.65)));
        $duration = max(1, min(5, (int) ($options['duration_years'] ?? 2)));
        $actions = $this->actions($change);

        $run = AnalysisRun::query()->create([
            'client_id' => $client->getKey(),
            'module' => AnalysisModule::RegulatoryImpact,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [AnalysisLens::Prescriptive->value],
            'data_quality_snapshot' => $score->toPayload(),
            'ai_model' => 'deterministic-regulatory-impact',
            'prompt_version' => '2026-05-wo51',
            'prompt_hash' => hash('sha256', $client->id.$change->id.json_encode($change->source, JSON_THROW_ON_ERROR)),
            'tokens_in' => 0,
            'tokens_out' => 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $finding = AnalysisFinding::query()->create([
            'analysis_run_id' => $run->getKey(),
            'client_id' => $client->getKey(),
            'lens' => AnalysisLens::Prescriptive,
            'severity' => $impact >= 50000 ? FindingSeverity::High : FindingSeverity::Medium,
            'title' => 'Regulatory change impact assessment',
            'body' => $this->body($change, $impact, $probability, $duration, $actions),
            'attributions' => $this->attributions($change),
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_NONE,
            'uncertainty' => Uncertainty::Medium,
            'data_quality_disclaimer' => $score->level === Client::DATA_QUALITY_HIGH
                ? null
                : "Data quality is {$score->label()} ({$score->score}/100): {$score->message()}",
            'bias_signals' => [],
        ]);

        $risk = $this->riskPv->rank($client, [[
            'title' => 'Regulatory change financial impact',
            'financial_impact' => $impact,
            'probability' => $probability,
            'duration_years' => $duration,
            'analysis_finding_id' => $finding->getKey(),
            'source_reference' => "learning_update:{$change->id}",
        ]]);

        if ($risk !== []) {
            $finding->forceFill(['pv_link_id' => $risk[0]->getKey()])->save();
        }

        return $run->refresh()->load('findings');
    }

    /**
     * @return array<int, string>
     */
    private function actions(LearningUpdate $change): array
    {
        $statute = strtolower((string) data_get($change->proposed_change, 'statute', ''));

        return match (true) {
            str_contains($statute, 'employment') => [
                'Review employment-agreement templates.',
                'Confirm consultation and record-keeping obligations.',
                'Update advisor compliance checklist before release.',
            ],
            str_contains($statute, 'health') => [
                'Review H&S policy evidence.',
                'Confirm officer due-diligence records.',
                'Update hazard and incident review cadence.',
            ],
            str_contains($statute, 'tax') => [
                'Review payroll and employer filing settings.',
                'Confirm IRD due-date and deduction obligations.',
                'Update client compliance calendar.',
            ],
            default => [
                'Review affected policy and evidence.',
                'Confirm financial impact with the client.',
                'Record advisor-approved compliance actions.',
            ],
        };
    }

    /**
     * @param  array<int, string>  $actions
     */
    private function body(LearningUpdate $change, float $impact, float $probability, int $duration, array $actions): string
    {
        return sprintf(
            '%s Estimated financial impact is NZD %s at %.0f%% probability over %d year(s). Compliance actions: %s',
            $change->summary,
            number_format($impact, 0),
            $probability * 100,
            $duration,
            implode(' ', $actions),
        );
    }

    /**
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function attributions(LearningUpdate $change): array
    {
        $sourceUrl = data_get($change->source, 'source_url');

        return [
            [
                'claim' => 'Regulatory impact was generated from a governed legislative-currency candidate.',
                'source_reference' => "learning_update:{$change->id}",
            ],
            [
                'claim' => 'Legislative source URL is retained for advisor review.',
                'source_reference' => is_string($sourceUrl) && $sourceUrl !== '' ? $sourceUrl : 'legislative_source:unavailable',
            ],
        ];
    }
}
