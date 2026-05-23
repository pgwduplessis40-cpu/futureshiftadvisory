<?php

declare(strict_types=1);

namespace App\Services\Integration\Fsp\Contracts;

interface FspClient
{
    /**
     * @return array<string, mixed>
     */
    public function lookup(string $fspNumber): array;
}
