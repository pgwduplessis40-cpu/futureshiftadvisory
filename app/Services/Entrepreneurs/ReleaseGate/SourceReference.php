<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class SourceReference implements \JsonSerializable
{
    public function __construct(
        public string $kind,
        public string $id,
        public string $location,
        public string $version,
    ) {}

    /** @return array{kind:string,id:string,location:string,version:string} */
    public function jsonSerialize(): array
    {
        return ['kind' => $this->kind, 'id' => $this->id, 'location' => $this->location, 'version' => $this->version];
    }
}
