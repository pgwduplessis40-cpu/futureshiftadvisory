<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

enum Audience: string
{
    case Internal = 'internal';
    case External = 'external';
}
