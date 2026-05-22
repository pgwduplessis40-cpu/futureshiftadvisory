<?php

declare(strict_types=1);

namespace App\Services\Analysis;

final class HolidaysActLiabilityCalculator
{
    /**
     * @return array{underpaid_hours:float, hourly_rate:float, gross_liability:float, remediation_buffer:float, total_liability:float}
     */
    public function calculate(float $underpaidHours, float $hourlyRate, float $bufferRate = 0.15): array
    {
        $hours = max(0.0, $underpaidHours);
        $rate = max(0.0, $hourlyRate);
        $buffer = max(0.0, $bufferRate);
        $gross = round($hours * $rate, 2);
        $remediationBuffer = round($gross * $buffer, 2);

        return [
            'underpaid_hours' => $hours,
            'hourly_rate' => $rate,
            'gross_liability' => $gross,
            'remediation_buffer' => $remediationBuffer,
            'total_liability' => round($gross + $remediationBuffer, 2),
        ];
    }
}
