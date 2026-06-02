<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\WelcomeMessage;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class WelcomeMessageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_super_admin_can_publish_a_welcome_message_and_it_is_audited(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.welcome-message.store'), [
                'body' => 'Kia ora {{contact_first_name}}, welcome to {{practice_name}}.',
            ])
            ->assertRedirect(route('admin.welcome-message.index', absolute: false));

        $message = WelcomeMessage::query()->firstOrFail();
        $this->assertSame(1, $message->version);
        $this->assertTrue($message->is_active);
        $this->assertSame($admin->getKey(), $message->created_by_user_id);
        $this->assertNotNull($message->activated_at);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'welcome_message.published',
            'subject_id' => $message->id,
        ]);
    }

    public function test_publishing_again_supersedes_the_prior_active_version(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)->post(route('admin.welcome-message.store'), [
            'body' => 'First version of the welcome message for clients.',
        ]);
        $this->actingAsMfa($admin)->post(route('admin.welcome-message.store'), [
            'body' => 'Second, improved version of the welcome message for clients.',
        ]);

        $this->assertSame(2, WelcomeMessage::query()->count());
        $this->assertSame(1, WelcomeMessage::query()->where('is_active', true)->count());

        $active = WelcomeMessage::query()->where('is_active', true)->firstOrFail();
        $this->assertSame(2, $active->version);
        $this->assertStringContainsString('Second, improved', (string) $active->body);

        $this->assertFalse(
            WelcomeMessage::query()->where('version', 1)->firstOrFail()->is_active,
        );
    }

    public function test_index_renders_the_active_message_and_preview(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)->post(route('admin.welcome-message.store'), [
            'body' => 'Kia ora {{contact_first_name}}, welcome to {{practice_name}}.',
        ]);

        $this->actingAsMfa($admin)
            ->get(route('admin.welcome-message.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/welcome-message/Index')
                ->where('current.version', 1)
                ->where('preview.has_message', true)
                ->has('placeholders')
                ->has('history', 1)
            );
    }

    public function test_short_body_is_rejected(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.welcome-message.store'), ['body' => 'too short'])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, WelcomeMessage::query()->count());
    }

    public function test_non_super_admin_cannot_view_or_publish(): void
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $this->actingAsMfa($advisor)
            ->get(route('admin.welcome-message.index'))
            ->assertForbidden();

        $this->actingAsMfa($advisor)
            ->post(route('admin.welcome-message.store'), [
                'body' => 'An advisor should not be able to publish this content.',
            ])
            ->assertForbidden();

        $this->assertSame(0, WelcomeMessage::query()->count());
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }
}
