<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class DocumentBlock implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $text,
        public SourceReference $source,
        public bool $generated = false,
        public bool $quotation = false,
        public bool $heading = false,
        public bool $internalInstruction = false,
    ) {}

    /** @return array{id:string,text:string,source:SourceReference,generated:bool,quotation:bool,heading:bool,internal_instruction:bool} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'text' => $this->text, 'source' => $this->source, 'generated' => $this->generated,
            'quotation' => $this->quotation, 'heading' => $this->heading, 'internal_instruction' => $this->internalInstruction];
    }
}
