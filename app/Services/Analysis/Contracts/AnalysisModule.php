<?php

declare(strict_types=1);

namespace App\Services\Analysis\Contracts;

use App\Enums\AnalysisModule as AnalysisModuleEnum;
use App\Models\Client;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Analysis\AnalysisFindingData;
use App\Services\DataQuality\DataQualityScore;

interface AnalysisModule
{
    public function module(): AnalysisModuleEnum;

    public function promptId(): string;

    /**
     * @return array<string, mixed>
     */
    public function promptInput(Client $client, DataQualityScore $score): array;

    /**
     * @return array<int, string>
     */
    public function sourceReferences(Client $client, DataQualityScore $score): array;

    /**
     * @return array<int, AnalysisFindingData>
     */
    public function mapFindings(Client $client, AiResponse $response, DataQualityScore $score): array;
}
