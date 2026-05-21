<?php

declare(strict_types=1);

namespace App\Services\Integration\Ird\Contracts;

interface IrdClient
{
    /**
     * @return array<string, mixed>
     */
    public function gstStatus(string $nzbn): array;
}
