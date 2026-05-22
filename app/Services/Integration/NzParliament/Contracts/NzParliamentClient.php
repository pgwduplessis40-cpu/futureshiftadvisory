<?php

declare(strict_types=1);

namespace App\Services\Integration\NzParliament\Contracts;

interface NzParliamentClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function legislativeChanges(): array;
}
