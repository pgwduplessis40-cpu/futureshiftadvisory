<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Enums\Permission as FsaPermission;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\User;
use App\Services\Security\InviteIssuer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class RbacMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_roles_match_the_documented_permission_matrix(): void
    {
        $this->seed(RoleSeeder::class);

        $matrix = RoleSeeder::rolePermissions();

        $this->assertSame(User::userTypes(), array_keys($matrix));
        $this->assertEqualsCanonicalizing(
            FsaPermission::values(),
            Permission::query()->pluck('name')->all(),
        );

        foreach ($matrix as $roleName => $expectedPermissions) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', FsaPermission::GUARD)
                ->firstOrFail();

            $this->assertEqualsCanonicalizing(
                $expectedPermissions,
                $role->permissions()->pluck('name')->all(),
                "Role [{$roleName}] permissions do not match the WO-07 matrix.",
            );
        }
    }

    public function test_junior_advisor_cannot_hit_restricted_capability_routes_directly(): void
    {
        $this->seed(RoleSeeder::class);

        $restrictedPermissions = [
            FsaPermission::QUESTIONNAIRES_PUBLISH,
            FsaPermission::PROPOSALS_RELEASE,
            FsaPermission::PAYMENTS_MANAGE,
            FsaPermission::REFERRALS_SEND,
            FsaPermission::LEARNING_UPDATES_APPROVE,
        ];

        foreach ($restrictedPermissions as $permission) {
            Route::middleware(['web', 'auth', 'permission:'.$permission->value])
                ->get('/_test/rbac/'.str_replace('.', '-', $permission->value), static fn () => response('ok'));
        }

        $juniorAdvisor = $this->userWithRole(User::TYPE_JUNIOR_ADVISOR);
        $superAdmin = $this->userWithRole(User::TYPE_SUPER_ADMIN);

        foreach ($restrictedPermissions as $permission) {
            $uri = '/_test/rbac/'.str_replace('.', '-', $permission->value);

            $this->actingAs($juniorAdvisor)
                ->get($uri)
                ->assertForbidden();

            $this->actingAs($superAdmin)
                ->get($uri)
                ->assertOk();
        }
    }

    public function test_dd_guest_is_token_only_and_not_a_user_role(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame('dd_guest', FsaPermission::DD_GUEST_TOKEN_TYPE);
        $this->assertSame(FsaPermission::DD_GUEST_TOKEN_TYPE, RoleSeeder::DD_GUEST_TOKEN_TYPE);
        $this->assertNotContains(FsaPermission::DD_GUEST_TOKEN_TYPE, User::userTypes());
        $this->assertFalse(Role::query()->where('name', FsaPermission::DD_GUEST_TOKEN_TYPE)->exists());
        $this->assertDatabaseMissing('users', ['user_type' => FsaPermission::DD_GUEST_TOKEN_TYPE]);

        Mail::fake();

        $this->expectException(ValidationException::class);

        app(InviteIssuer::class)->issue(
            email: 'dd-guest@example.com',
            targetUserType: FsaPermission::DD_GUEST_TOKEN_TYPE,
            targetRole: FsaPermission::DD_GUEST_TOKEN_TYPE,
        );
    }

    public function test_accepting_an_invite_assigns_the_matching_spatie_role(): void
    {
        $this->seed(RoleSeeder::class);
        Mail::fake();

        $issued = app(InviteIssuer::class)->issue(
            email: 'client-primary@example.com',
            targetUserType: User::TYPE_CLIENT_PRIMARY,
            targetRole: User::TYPE_CLIENT_PRIMARY,
        );

        $this->post(route('invite.store', $issued->plainToken), [
            'name' => 'Client Primary',
            'password' => 'A-secure-password-123',
            'password_confirmation' => 'A-secure-password-123',
        ])->assertRedirect(route('mfa.setup', absolute: false));

        $user = User::query()->where('email', 'client-primary@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole(User::TYPE_CLIENT_PRIMARY));
        $this->assertTrue($user->can(FsaPermission::CLIENTS_VIEW->value));
    }

    public function test_policies_delegate_to_the_seeded_permissions(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(User::TYPE_ADVISOR);
        $juniorAdvisor = $this->userWithRole(User::TYPE_JUNIOR_ADVISOR);
        $superAdmin = $this->userWithRole(User::TYPE_SUPER_ADMIN);

        $this->assertTrue(Gate::forUser($advisor)->allows('verify', Document::class));
        $this->assertFalse(Gate::forUser($juniorAdvisor)->allows('verify', Document::class));

        $this->assertTrue(Gate::forUser($advisor)->allows('publish', 'App\\Models\\Questionnaire'));
        $this->assertFalse(Gate::forUser($juniorAdvisor)->allows('publish', 'App\\Models\\Questionnaire'));

        $this->assertTrue(Gate::forUser($superAdmin)->allows('publish', 'App\\Models\\TermsVersion'));
        $this->assertFalse(Gate::forUser($advisor)->allows('publish', 'App\\Models\\TermsVersion'));

        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', AuditEvent::class));
        $this->assertFalse(Gate::forUser($juniorAdvisor)->allows('viewAny', AuditEvent::class));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'user_type' => $role,
            'primary_role' => $role,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
