<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

enum Uncertainty: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case None = 'none';
}
