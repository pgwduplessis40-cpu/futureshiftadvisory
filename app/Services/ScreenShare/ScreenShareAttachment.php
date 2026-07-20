<?php

declare(strict_types=1);

namespace App\Services\ScreenShare;

final readonly class ScreenShareAttachment
{
    public function __construct(
        public string $basis,
        public ?string $advisorTeamId = null,
    ) {}

    /**
     * @return array{path:string, advisor_team_id:?string, evaluated_at:string}
     */
    public function auditPayload(): array
    {
        return [
            'path' => $this->basis,
            'advisor_team_id' => $this->advisorTeamId,
            'evaluated_at' => now()->toIso8601String(),
        ];
    }
}
