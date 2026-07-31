<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class OperationalHealthFixtures
{
    public const CLIENT_SOURCE = 'operational_health_fixture';

    /**
     * @return array<int, string>
     */
    public static function userEmails(): array
    {
        return collect(config('operational_health.users', []))
            ->filter(static fn (mixed $email): bool => is_string($email) && trim($email) !== '')
            ->map(static fn (string $email): string => Str::lower(trim($email)))
            ->unique()
            ->values()
            ->all();
    }
}
