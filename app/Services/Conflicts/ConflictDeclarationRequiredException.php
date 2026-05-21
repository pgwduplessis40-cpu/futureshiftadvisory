<?php

declare(strict_types=1);

namespace App\Services\Conflicts;

use App\Models\ConflictDeclaration;

final class ConflictDeclarationRequiredException extends \RuntimeException
{
    public function __construct(
        public readonly string $referralType,
        public readonly ?ConflictDeclaration $declaration = null,
    ) {
        parent::__construct($this->messageFor($referralType, $declaration));
    }

    private function messageFor(string $referralType, ?ConflictDeclaration $declaration): string
    {
        $label = str_replace('_', ' ', $referralType);

        if (! $declaration instanceof ConflictDeclaration) {
            return "A conflict declaration is required before {$label}.";
        }

        return "A fresh conflict declaration is required before {$label}.";
    }
}
