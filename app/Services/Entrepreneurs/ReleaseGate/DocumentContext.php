<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class DocumentContext
{
    /** @param list<DocumentBlock> $blocks */
    public function __construct(
        public SourceVersion $source,
        public FactSet $facts,
        public string $document,
        public Audience $audience,
        public array $blocks = [],
        public bool $renderedTextComplete = false,
    ) {
        if (! in_array($document, ['plan', 'budget', 'brief'], true)) {
            throw new \InvalidArgumentException('Unknown release document.');
        }
    }

    public function hash(): string
    {
        return CanonicalJson::hash([$this->source, $this->facts, $this->document, $this->audience->value, $this->blocks, $this->renderedTextComplete]);
    }
}
