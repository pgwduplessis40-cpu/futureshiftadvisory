<?php

declare(strict_types=1);

namespace App\Support\Reports;

use Illuminate\Support\Str;

final class SourceReferenceLabeler
{
    public static function label(string $reference, ?string $claim = null): string
    {
        $reference = trim($reference);
        $claim = is_string($claim) ? trim($claim) : '';
        $base = self::baseLabel($reference);

        if ($claim !== '' && ! self::looksLikeMachineReference($claim)) {
            return $base === '' ? $claim : "{$claim} ({$base})";
        }

        return $base !== '' ? $base : 'Platform evidence record';
    }

    private static function baseLabel(string $reference): string
    {
        if ($reference === '') {
            return '';
        }

        [$type, $id, $detail] = array_pad(explode(':', $reference, 3), 3, '');
        $detail = $detail !== '' ? $detail : $id;

        return match ($type) {
            'financial_snapshot' => self::financialSnapshotLabel($detail),
            'economic_indicator' => self::detailLabel($detail, 'Economic indicator'),
            'questionnaire_answer' => 'Client questionnaire answer',
            'questionnaire_response' => 'Client questionnaire response',
            'questionnaire_set' => 'Client questionnaire set',
            'questionnaire_section' => self::detailLabel($detail, 'Questionnaire section'),
            'document' => 'Uploaded document',
            'document_verification' => 'Document verification result',
            'business_valuation' => 'Business valuation calculation',
            'dd_valuation' => 'Due diligence valuation',
            'dd_engagement' => 'Due diligence engagement record',
            'dd_data_room_item' => 'Due diligence data-room evidence',
            'dd_risk_register' => 'Due diligence risk register',
            'pv_calculation', 'baseline_pv_calculation' => 'Present value calculation',
            'pv_waterfall' => 'Value waterfall calculation',
            'proposal' => 'Advisor proposal',
            'analysis_findings' => 'Advisor analysis findings',
            'analysis_finding' => 'Advisor analysis finding',
            'improvement_opportunity' => 'Improvement opportunity',
            'client_funder_records' => 'Client funder records',
            'npo_value_calculations' => 'NPO value calculation',
            'npo_dimension_scores' => 'NPO health assessment',
            'npo_engagement' => 'NPO engagement record',
            'npo_social_enterprise_scorecard' => 'Social enterprise scorecard',
            'npo_tension_analyses' => 'Social enterprise tension analysis',
            'valuation_disclosures' => 'Valuation scope disclosure',
            'statute' => self::statuteLabel($reference),
            'working_capital_adjustment' => 'Working-capital adjustment',
            'industry_wacc_data' => 'Industry WACC reference data',
            'industry_wacc' => 'Industry WACC assumption',
            'fsa_platform_data' => 'Future Shift platform benchmark data',
            'learning_update' => 'Learning update record',
            'advisor' => 'Advisor-entered assumption',
            'client' => 'Client record',
            'test' => 'Test fixture evidence',
            default => self::detailLabel($type, 'Platform evidence record'),
        };
    }

    private static function financialSnapshotLabel(string $detail): string
    {
        $field = Str::of($detail)
            ->after(':')
            ->replace(['_', '.'], ' ')
            ->squish()
            ->toString();

        return $field === '' ? 'Accounting snapshot' : 'Accounting snapshot: '.$field;
    }

    private static function detailLabel(string $detail, string $fallback): string
    {
        $label = Str::of($detail)
            ->replace(['_', '-', '.'], ' ')
            ->squish()
            ->title()
            ->toString();

        return $label === '' ? $fallback : "{$fallback}: {$label}";
    }

    private static function statuteLabel(string $reference): string
    {
        $parts = explode(':', $reference);
        $statute = (string) end($parts);

        return self::detailLabel($statute, 'Statutory reference');
    }

    private static function looksLikeMachineReference(string $value): bool
    {
        return preg_match('/^[a-z0-9_]+:[^\s]+/i', $value) === 1;
    }
}
