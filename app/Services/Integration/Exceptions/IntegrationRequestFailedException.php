<?php

declare(strict_types=1);

namespace App\Services\Integration\Exceptions;

use RuntimeException;
use Throwable;

final class IntegrationRequestFailedException extends RuntimeException
{
    public function __construct(
        public readonly string $service,
        public readonly string $operation,
        public readonly string $correlationId,
        ?Throwable $previous = null,
    ) {
        parent::__construct("Integration [{$service}] failed during {$operation}. Correlation ID: {$correlationId}.", 0, $previous);
    }

    public static function forService(string $service, string $operation, string $correlationId): self
    {
        return new self($service, $operation, $correlationId);
    }
}
