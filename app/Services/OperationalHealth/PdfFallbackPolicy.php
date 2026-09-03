<?php

declare(strict_types=1);

namespace App\Services\OperationalHealth;

use App\Models\OperationalHealthCheckResult;
use App\Services\Pdf\SimpleTextPdf;

final class PdfFallbackPolicy
{
    /**
     * @param  array<string, mixed>  $probe
     * @return array{detected:bool,is_failure:bool,status:string,marker:string|null}
     */
    public function forProbe(?string $expectedContentType, array $probe): array
    {
        $detected = $expectedContentType !== null
            && str_starts_with($expectedContentType, 'application/pdf')
            && (bool) ($probe['fallback_pdf_detected'] ?? false);
        $isFailure = $detected && (bool) config('operational_health.fail_on_pdf_fallback', false);

        return [
            'detected' => $detected,
            'is_failure' => $isFailure,
            'status' => ! $detected
                ? OperationalHealthCheckResult::STATUS_PASSED
                : ($isFailure ? OperationalHealthCheckResult::STATUS_FAILED : OperationalHealthCheckResult::STATUS_WARNING),
            'marker' => $detected ? SimpleTextPdf::FALLBACK_MARKER : null,
        ];
    }
}
