<?php

declare(strict_types=1);

namespace App\Services\Integration\Iponz\Contracts;

interface IponzClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function intellectualProperty(string $name, ?string $nzbn = null): array;
}
