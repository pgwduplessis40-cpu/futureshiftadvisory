<?php

declare(strict_types=1);

namespace App\Services\ScreenShare;

final readonly class ClientPortalContext
{
    public function __construct(
        public ?string $clientId,
        public string $routeKey,
        public ?string $entrepreneurProfileId = null,
    ) {}
}
