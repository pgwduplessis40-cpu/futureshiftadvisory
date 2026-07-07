<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Enums\AnalysisLens;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Services\Analysis\Modules\FinancialAnalysis;
use App\Services\Pv\ImprovementPv;

final class FinancialAnalysisRunner
{
    public function __construct(
        private readonly AnalysisRunner $runner,
        private readonly FinancialAnalysis $module,
        private readonly ImprovementPv $improvements,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function run(Client $client, array $options = []): AnalysisRun
    {
        $run = $this->runner->run($client, $this->module, $options);

        if ($run->status !== AnalysisRun::STATUS_COMPLETED) {
            return $run;
        }

        $finding = $run->findings()
            ->where('lens', AnalysisLens::Prescriptive)
            ->where('title', 'Financial improvement opportunity')
            ->first();
        $finding ??= $run->findings()
            ->where('lens', AnalysisLens::Prescriptive)
            ->latest()
            ->first();
        $opportunity = $this->module->improvementOpportunity($client);

        if ($finding instanceof AnalysisFinding && $opportunity !== null) {
            $ranked = $this->improvements->rank($client, [[
                ...$opportunity,
                'analysis_finding_id' => $finding->getKey(),
            ]]);

            if ($ranked !== []) {
                $finding->forceFill(['pv_link_id' => $ranked[0]->getKey()])->save();
            }
        }

        return $run->refresh()->load('findings');
    }
}
