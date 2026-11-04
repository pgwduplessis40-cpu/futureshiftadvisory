<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

interface Rule
{
    public function id(): string;

    /** @return list<Subcheck> */
    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array;
}
