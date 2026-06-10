<?php

declare(strict_types=1);

namespace App\Services\Integration\Ppsr;

use App\Services\Integration\Ppsr\Contracts\PpsrClient;
use Throwable;

final class FallbackPpsrClient implements PpsrClient
{
    public function __construct(
        private readonly LivePpsrClient $live,
        private readonly FakePpsrClient $fake,
    ) {}

    public function securityInterests(string $nzbn): array
    {
        try {
            return $this->live->securityInterests($nzbn);
        } catch (Throwable) {
            return $this->fake->securityInterests($nzbn);
        }
    }
}
