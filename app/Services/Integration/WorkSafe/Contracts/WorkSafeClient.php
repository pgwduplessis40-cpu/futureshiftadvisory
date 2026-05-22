<?php

declare(strict_types=1);

namespace App\Services\Integration\WorkSafe\Contracts;

interface WorkSafeClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function legislativeChanges(): array;
}
