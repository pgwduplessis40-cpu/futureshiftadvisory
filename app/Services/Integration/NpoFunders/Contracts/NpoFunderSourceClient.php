<?php

declare(strict_types=1);

namespace App\Services\Integration\NpoFunders\Contracts;

interface NpoFunderSourceClient
{
    /**
     * @return array<string, mixed>
     */
    public function fetch(string $source): array;
}
