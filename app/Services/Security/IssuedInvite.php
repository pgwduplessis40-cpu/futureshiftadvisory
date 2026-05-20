<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\InviteToken;

final readonly class IssuedInvite
{
    public function __construct(
        public InviteToken $invite,
        public string $plainToken,
        public string $acceptUrl,
    ) {}
}
