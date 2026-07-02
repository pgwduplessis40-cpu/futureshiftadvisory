<?php

declare(strict_types=1);

namespace App\Services\Ai\Prompts;

interface GovernancePreambleProvider
{
    /**
     * @return array{text:string,version:string}
     */
    public function active(): array;
}
