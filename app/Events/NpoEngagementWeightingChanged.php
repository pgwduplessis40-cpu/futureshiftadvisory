<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class NpoEngagementWeightingChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $npoEngagementId,
        public readonly string $clientId,
        public readonly bool $socialEnterprise,
        public readonly ?string $socialEnterpriseType,
        public readonly ?int $commercialWeight,
        public readonly ?int $missionWeight,
        public readonly ?string $actorUserId,
    ) {}
}
