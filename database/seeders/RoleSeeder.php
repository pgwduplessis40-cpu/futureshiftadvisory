<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission as FsaPermission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RoleSeeder extends Seeder
{
    public const DD_GUEST_TOKEN_TYPE = FsaPermission::DD_GUEST_TOKEN_TYPE;

    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::rolePermissions() as $roleName => $permissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => FsaPermission::GUARD,
            ]);

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rolePermissions(): array
    {
        $matrix = [];

        foreach (User::userTypes() as $role) {
            $matrix[$role] = FsaPermission::valuesForRole($role);
        }

        return $matrix;
    }
}
