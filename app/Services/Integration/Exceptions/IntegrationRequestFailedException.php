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
        public readonly ?string $detail = null,
        ?Throwable $previous = null,
    ) {
        $detailMessage = $detail === null || trim($detail) === ''
            ? ''
            : " Detail: {$detail}.";

        parent::__construct("Integration [{$service}] failed during {$operation}.{$detailMessage} Correlation ID: {$correlationId}.", 0, $previous);
    }

    public static function forService(string $service, string $operation, string $correlationId, ?string $detail = null): self
    {
        return new self($service, $operation, $correlationId, $detail);
    }
}
