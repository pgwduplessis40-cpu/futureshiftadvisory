<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class RenderedDraft
{
    public function __construct(public string $bytes, public string $renderer, public ?string $candidateHtml = null) {}
}
