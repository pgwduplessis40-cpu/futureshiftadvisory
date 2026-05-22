<?php

declare(strict_types=1);

namespace App\Services\Integration\Ird;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Ird\Contracts\IrdClient;

final class FallbackIrdClient implements IrdClient
{
    public function __construct(
        private readonly LiveIrdClient $live,
        private readonly FakeIrdClient $fake,
    ) {}

    public function gstStatus(string $nzbn): array
    {
        try {
            return $this->live->gstStatus($nzbn);
        } catch (IntegrationDisabledException) {
            return $this->fake->gstStatus($nzbn);
        }
    }

    public function legislativeChanges(): array
    {
        try {
            return $this->live->legislativeChanges();
        } catch (IntegrationDisabledException) {
            return $this->fake->legislativeChanges();
        }
    }
}
