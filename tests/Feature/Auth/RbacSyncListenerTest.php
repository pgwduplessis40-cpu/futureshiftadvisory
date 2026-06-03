<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Listeners\SyncRbacAfterMigrations;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class RbacSyncListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_migration_outside_testing_resyncs_missing_permissions(): void
    {
        $this->seed(RoleSeeder::class);
        $this->dropPermission('board.manage');
        $this->assertFalse($this->permissionExists('board.manage'));

        $app = Mockery::mock(Application::class);
        $app->shouldReceive('environment')->with('testing')->andReturn(false);
        $app->shouldReceive('make')->with(RoleSeeder::class)->andReturn(app(RoleSeeder::class));

        (new SyncRbacAfterMigrations($app))->handle(new MigrationsEnded('up'));

        $this->assertTrue($this->permissionExists('board.manage'));
        $this->assertSame(
            count(\App\Enums\Permission::cases()),
            Permission::query()->count(),
        );
    }

    public function test_skips_on_rollback(): void
    {
        $this->seed(RoleSeeder::class);
        $this->dropPermission('board.manage');

        $app = Mockery::mock(Application::class);
        $app->shouldReceive('environment')->never();
        $app->shouldReceive('make')->never();

        (new SyncRbacAfterMigrations($app))->handle(new MigrationsEnded('down'));

        $this->assertFalse($this->permissionExists('board.manage'));
    }

    public function test_skips_in_testing_environment(): void
    {
        $this->seed(RoleSeeder::class);
        $this->dropPermission('board.manage');

        $app = Mockery::mock(Application::class);
        $app->shouldReceive('environment')->with('testing')->andReturn(true);
        $app->shouldReceive('make')->never();

        (new SyncRbacAfterMigrations($app))->handle(new MigrationsEnded('up'));

        $this->assertFalse($this->permissionExists('board.manage'));
    }

    private function dropPermission(string $name): void
    {
        Permission::query()->where('name', $name)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionExists(string $name): bool
    {
        return Permission::query()->where('name', $name)->exists();
    }
}
