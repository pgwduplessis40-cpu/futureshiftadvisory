<?php

declare(strict_types=1);

namespace App\Services\Integration\Exceptions;

use RuntimeException;

final class IntegrationRequestFailedException extends RuntimeException
{
    public static function forService(string $service, string $operation, string $correlationId): self
    {
        return new self("Integration [{$service}] failed during {$operation}. Correlation ID: {$correlationId}.");
    }
}
