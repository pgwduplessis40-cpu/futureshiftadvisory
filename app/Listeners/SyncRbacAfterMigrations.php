<?php

declare(strict_types=1);

namespace App\Listeners;

use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\MigrationsEnded;

/**
 * Keeps Spatie roles/permissions in lockstep with the App\Enums\Permission enum
 * after every `migrate`, so a newly added permission is granted to its roles
 * without anyone remembering to re-seed (the cause of a super-admin 403 on new
 * admin pages). RoleSeeder is idempotent (firstOrCreate + syncPermissions).
 *
 * Skipped during the test suite — tests seed RBAC explicitly per case — and on
 * rollbacks.
 */
final class SyncRbacAfterMigrations
{
    public function __construct(private readonly Application $app) {}

    public function handle(MigrationsEnded $event): void
    {
        if ($event->method !== 'up') {
            return;
        }

        if ($this->app->environment('testing')) {
            return;
        }

        $this->app->make(RoleSeeder::class)->run();
    }
}
