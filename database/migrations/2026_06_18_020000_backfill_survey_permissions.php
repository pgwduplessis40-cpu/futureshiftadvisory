<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->upsertSurveyPermissions();

        $assignments = [
            User::TYPE_SUPER_ADMIN => [
                Permission::SURVEYS_MANAGE->value,
                Permission::SURVEYS_VIEW->value,
            ],
            User::TYPE_ADVISOR => [
                Permission::SURVEYS_VIEW->value,
            ],
            User::TYPE_JUNIOR_ADVISOR => [
                Permission::SURVEYS_VIEW->value,
            ],
            User::TYPE_ENTREPRENEUR_MENTOR => [
                Permission::SURVEYS_VIEW->value,
            ],
        ];

        foreach ($assignments as $roleName => $permissions) {
            $roleId = DB::table('roles')
                ->where('name', $roleName)
                ->where('guard_name', Permission::GUARD)
                ->value('id');

            if ($roleId === null) {
                continue;
            }

            foreach ($permissions as $permissionName) {
                $permissionId = DB::table('permissions')
                    ->where('name', $permissionName)
                    ->where('guard_name', Permission::GUARD)
                    ->value('id');

                if ($permissionId === null) {
                    continue;
                }

                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! $this->permissionTablesExist()) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', [
                Permission::SURVEYS_MANAGE->value,
                Permission::SURVEYS_VIEW->value,
            ])
            ->where('guard_name', Permission::GUARD)
            ->pluck('id')
            ->all();

        if ($permissionIds !== []) {
            DB::table('role_has_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();

            DB::table('permissions')
                ->whereIn('id', $permissionIds)
                ->delete();
        }

        $this->forgetPermissionCache();
    }

    private function upsertSurveyPermissions(): void
    {
        if (! $this->permissionTablesExist()) {
            return;
        }

        foreach ([Permission::SURVEYS_MANAGE, Permission::SURVEYS_VIEW] as $permission) {
            DB::table('permissions')->updateOrInsert(
                [
                    'name' => $permission->value,
                    'guard_name' => Permission::GUARD,
                ],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    private function permissionTablesExist(): bool
    {
        return Schema::hasTable('permissions')
            && Schema::hasTable('roles')
            && Schema::hasTable('role_has_permissions');
    }

    private function forgetPermissionCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
