<?php

declare(strict_types=1);

namespace App\Services\Integration\Nzbn\Contracts;

interface NzbnClient
{
    /**
     * @return array<string, mixed>
     */
    public function lookupByNzbn(string $nzbn): array;
}
