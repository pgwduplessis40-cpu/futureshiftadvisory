<?php

declare(strict_types=1);

namespace App\Services\NzTools;

use App\Models\NzToolConnection;
use App\Services\Integration\BusinessTools\Contracts\NzBusinessToolClient;
use App\Services\Integration\Cin7\Contracts\Cin7Client;
use App\Services\Integration\EmploymentHero\Contracts\EmploymentHeroClient;
use App\Services\Integration\Tradify\Contracts\TradifyClient;
use InvalidArgumentException;

final class NzToolClientResolver
{
    public function __construct(
        private readonly EmploymentHeroClient $employmentHero,
        private readonly Cin7Client $cin7,
        private readonly TradifyClient $tradify,
    ) {}

    public function client(string $provider): NzBusinessToolClient
    {
        return match ($provider) {
            NzToolConnection::PROVIDER_EMPLOYMENT_HERO => $this->employmentHero,
            NzToolConnection::PROVIDER_CIN7 => $this->cin7,
            NzToolConnection::PROVIDER_TRADIFY => $this->tradify,
            default => throw new InvalidArgumentException("Unsupported NZ business tool provider [{$provider}]."),
        };
    }
}
