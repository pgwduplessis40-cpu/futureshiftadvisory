<?php

declare(strict_types=1);

namespace App\Services\Integration\Linz\Contracts;

interface LinzClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function titleInterests(string $nzbn, ?string $address = null): array;
}
