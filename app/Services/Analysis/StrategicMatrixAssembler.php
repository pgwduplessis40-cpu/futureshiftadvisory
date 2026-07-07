<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\ImprovementOpportunity;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireResponse;
use App\Models\RiskCost;

final class StrategicMatrixAssembler
{
    /**
     * @return array<string, mixed>
     */
    public function assemble(Client $client): array
    {
        $evidence = $this->evidence($client);
        $text = strtolower(implode(' ', array_map(
            static fn (array $item): string => (string) $item['text'],
            $evidence,
        )));
        $improvement = ImprovementOpportunity::query()
            ->where('client_id', $client->getKey())
            ->active()
            ->orderByDesc('pv_of_impact')
            ->first();
        $risk = RiskCost::query()
            ->where('client_id', $client->getKey())
            ->active()
            ->orderByDesc('pv_of_cost')
            ->first();

        return [
            'swot' => [
                'strengths' => [$this->strength($text)],
                'weaknesses' => [$this->weakness($text)],
                'opportunities' => [$this->opportunity($text, $improvement)],
                'threats' => [$this->threat($text, $risk)],
            ],
            'tows' => [
                'so' => ['Use the strongest capability to pursue the highest-value opportunity.'],
                'wo' => ['Close the clearest weakness before scaling the opportunity.'],
                'st' => ['Use existing strengths to reduce the most material external threat.'],
                'wt' => ['Set a defensive action for weaknesses exposed by the threat profile.'],
            ],
            'maps' => [
                'market' => [$this->contains($text, ['market', 'customer', 'segment']) ? 'Market evidence is present.' : 'Market evidence needs sharpening.'],
                'advantage' => [$this->contains($text, ['brand', 'specialist', 'quality', 'relationship']) ? 'Competitive advantage evidence is present.' : 'Competitive advantage needs proof.'],
                'priorities' => [$this->priority($improvement, $risk)],
                'systems' => [$this->contains($text, ['system', 'process', 'automation']) ? 'System/process evidence is present.' : 'System/process evidence is incomplete.'],
            ],
            'pv' => [
                'top_improvement_id' => $improvement?->id,
                'top_improvement_title' => $improvement?->title,
                'top_improvement_pv' => $improvement?->pv_of_impact,
                'top_risk_id' => $risk?->id,
                'top_risk_title' => $risk?->title,
                'top_risk_pv' => $risk?->pv_of_cost,
            ],
            'attributions' => $this->attributions($client, $evidence, $improvement, $risk),
        ];
    }

    private function strength(string $text): string
    {
        if ($this->contains($text, ['brand', 'relationship', 'repeat', 'quality'])) {
            return 'Client evidence points to brand, relationship, repeat-work, or quality strengths.';
        }

        return 'Strength evidence is present but needs advisor validation.';
    }

    private function weakness(string $text): string
    {
        if ($this->contains($text, ['cash', 'capacity', 'manual', 'slow', 'margin'])) {
            return 'Client evidence points to cash, capacity, manual-process, speed, or margin weaknesses.';
        }

        return 'Weakness evidence is present but not yet quantified.';
    }

    private function opportunity(string $text, ?ImprovementOpportunity $improvement): string
    {
        if ($improvement instanceof ImprovementOpportunity) {
            return "PV-ranked opportunity: {$improvement->title} (NZD ".number_format($improvement->pv_of_impact, 0).').';
        }

        if ($this->contains($text, ['growth', 'automation', 'pricing', 'new market'])) {
            return 'Client evidence points to growth, automation, pricing, or new-market opportunity.';
        }

        return 'Opportunity evidence needs PV quantification.';
    }

    private function threat(string $text, ?RiskCost $risk): string
    {
        if ($risk instanceof RiskCost) {
            return "PV-ranked risk: {$risk->title} (NZD ".number_format($risk->pv_of_cost, 0).').';
        }

        if ($this->contains($text, ['competitor', 'compliance', 'supplier', 'wage'])) {
            return 'Client evidence points to competitor, compliance, supplier, or wage pressure.';
        }

        return 'Threat evidence needs risk-cost quantification.';
    }

    private function priority(?ImprovementOpportunity $improvement, ?RiskCost $risk): string
    {
        if ($improvement instanceof ImprovementOpportunity && $risk instanceof RiskCost) {
            return $improvement->pv_of_impact >= $risk->pv_of_cost
                ? "Prioritise {$improvement->title}; it carries the highest PV value."
                : "Prioritise {$risk->title}; it carries the highest PV exposure.";
        }

        if ($improvement instanceof ImprovementOpportunity) {
            return "Prioritise {$improvement->title}; it is the top PV opportunity.";
        }

        if ($risk instanceof RiskCost) {
            return "Prioritise {$risk->title}; it is the top PV risk.";
        }

        return 'Priorities need PV evidence before sequencing.';
    }

    /**
     * @return array<int, array{source_reference:string, text:string}>
     */
    private function evidence(Client $client): array
    {
        $questionnaireEvidence = QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->with('answers.question')
            ->latest('submitted_at')
            ->latest()
            ->limit(3)
            ->get()
            ->flatMap(function (QuestionnaireResponse $response): array {
                return $response->answers
                    ->map(fn (QuestionnaireAnswer $answer): array => [
                        'source_reference' => "questionnaire_answer:{$answer->id}",
                        'text' => trim((string) $answer->question?->prompt.' '.(is_array($answer->value) ? json_encode($answer->value) : $answer->value)),
                    ])
                    ->filter(fn (array $item): bool => $item['text'] !== '')
                    ->all();
            })
            ->values()
            ->all();

        $findings = AnalysisFinding::query()
            ->where('client_id', $client->getKey())
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (AnalysisFinding $finding): array => [
                'source_reference' => "analysis_finding:{$finding->id}",
                'text' => $finding->title.' '.$finding->body,
            ])
            ->all();

        return [...$questionnaireEvidence, ...$findings];
    }

    /**
     * @param  array<int, array{source_reference:string, text:string}>  $evidence
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function attributions(
        Client $client,
        array $evidence,
        ?ImprovementOpportunity $improvement,
        ?RiskCost $risk,
    ): array {
        $attributions = array_map(
            static fn (array $item): array => [
                'claim' => 'Strategic matrix evidence comes from client inputs or prior governed findings.',
                'source_reference' => $item['source_reference'],
            ],
            $evidence,
        );

        if ($improvement instanceof ImprovementOpportunity) {
            $attributions[] = [
                'claim' => 'Strategic opportunity priority references the top PV improvement.',
                'source_reference' => "improvement_opportunity:{$improvement->id}",
            ];
        }

        if ($risk instanceof RiskCost) {
            $attributions[] = [
                'claim' => 'Strategic threat priority references the top PV risk.',
                'source_reference' => "risk_cost:{$risk->id}",
            ];
        }

        if ($attributions === []) {
            $attributions[] = [
                'claim' => 'Client profile identifies the strategic-matrix subject.',
                'source_reference' => "client:{$client->id}",
            ];
        }

        return $attributions;
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function contains(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }
}
