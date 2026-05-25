<?php

declare(strict_types=1);

namespace App\Support\Methodology;

interface ProvidesMethodology
{
    /**
     * @return array<int, string>
     */
    public static function methodologyIds(): array;
}
