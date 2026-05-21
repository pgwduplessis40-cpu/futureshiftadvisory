<?php

declare(strict_types=1);

namespace App\Services\Terms;

final readonly class SignedAcceptanceArtifact
{
    /**
     * @param  array{v:int, alg:string, kid:string}  $envelopeMeta
     */
    public function __construct(
        public string $path,
        public int $byteSize,
        public string $sha256Envelope,
        public array $envelopeMeta,
    ) {}
}
