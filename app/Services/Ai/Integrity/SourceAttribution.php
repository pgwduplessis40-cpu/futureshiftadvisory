<?php

declare(strict_types=1);

namespace App\Services\Ai\Integrity;

use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Exceptions\MissingAttributionException;

final class SourceAttribution
{
    public function validate(AiResponse $response): void
    {
        if (trim($response->text) === '') {
            throw new MissingAttributionException('AI response text is empty.');
        }

        if ($response->attributions === []) {
            throw new MissingAttributionException(
                'AI response contains text but no source attributions.'
            );
        }

        foreach ($response->attributions as $index => $attribution) {
            if (trim($attribution['claim'] ?? '') === '') {
                throw new MissingAttributionException(
                    "AI response attribution [{$index}] is missing a claim."
                );
            }

            if (trim($attribution['source_reference'] ?? '') === '') {
                throw new MissingAttributionException(
                    "AI response attribution [{$index}] is missing a source reference."
                );
            }
        }
    }
}
