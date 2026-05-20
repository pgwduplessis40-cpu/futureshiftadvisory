<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission as FsaPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (FsaPermission::cases() as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission->value,
                'guard_name' => FsaPermission::GUARD,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
