<?php

declare(strict_types=1);

namespace App\Support\Telemetry;

/**
 * The complete client-error contract. Do not add identity, request payload,
 * financial, document, or free-form context fields to this DTO.
 *
 * @phpstan-type BrowserMetadata array{family:string,platform:string,viewport:string}
 */
final readonly class ClientErrorEvent
{
    /** @param BrowserMetadata $browser */
    public function __construct(
        public string $releaseSha,
        public string $route,
        public string $feature,
        public array $browser,
        public string $errorFingerprint,
        public string $sanitizedStack,
    ) {}

    /** @return array{release_sha:string,route:string,feature:string,browser:array{family:string,platform:string,viewport:string},error_fingerprint:string,sanitized_stack:string} */
    public function toArray(): array
    {
        return [
            'release_sha' => $this->releaseSha,
            'route' => $this->route,
            'feature' => $this->feature,
            'browser' => $this->browser,
            'error_fingerprint' => $this->errorFingerprint,
            'sanitized_stack' => $this->sanitizedStack,
        ];
    }
}
