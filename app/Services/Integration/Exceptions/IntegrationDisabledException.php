<?php

declare(strict_types=1);

namespace App\Services\Integration\Exceptions;

use RuntimeException;

final class IntegrationDisabledException extends RuntimeException
{
    public static function forService(string $service): self
    {
        return new self("Integration [{$service}] is disabled.");
    }
}
