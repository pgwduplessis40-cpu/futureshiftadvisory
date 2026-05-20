<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

interface AiClient
{
    public function analyse(PromptEnvelope $prompt): AiResponse;

    public function verifyDocument(PromptEnvelope $prompt): AiResponse;

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse;

    public function summarise(PromptEnvelope $prompt): AiResponse;

    public function redFlag(PromptEnvelope $prompt): AiResponse;
}
