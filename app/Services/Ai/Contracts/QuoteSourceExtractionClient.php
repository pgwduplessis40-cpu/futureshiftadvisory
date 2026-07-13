<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

interface QuoteSourceExtractionClient
{
    public function extractQuoteSource(PromptEnvelope $prompt): AiResponse;
}
