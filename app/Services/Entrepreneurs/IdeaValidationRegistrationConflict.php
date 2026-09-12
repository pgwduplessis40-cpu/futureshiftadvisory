<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use RuntimeException;

final class IdeaValidationRegistrationConflict extends RuntimeException
{
    public const EXISTING_ACCOUNT = 'existing_account';

    public const EXISTING_PROFILE = 'existing_profile';

    public function __construct(public readonly string $conflict)
    {
        parent::__construct('An Idea Validation registration conflicts with an existing record.');
    }
}
