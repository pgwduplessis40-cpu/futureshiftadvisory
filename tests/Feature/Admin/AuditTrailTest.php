<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_writer_records_actor_key_for_integer_user_ids(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();

        app(AuditWriter::class)->record('audit.actor_key_probe', actor: $admin);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'audit.actor_key_probe',
            'actor_user_key' => (string) $admin->getKey(),
            'actor_role' => User::TYPE_SUPER_ADMIN,
        ]);
    }

    public function test_super_admin_can_search_audit_trail_by_actor_and_action(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $actor = User::factory()->withTwoFactor()->create([
            'name' => 'Aroha Reviewer',
            'email' => 'aroha.reviewer@example.test',
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $actor->assignRole(User::TYPE_ADVISOR);

        AuditEvent::query()->create([
            'id' => (string) Str::uuid(),
            'occurred_at' => now()->subMinute(),
            'actor_user_key' => (string) $actor->getKey(),
            'actor_role' => User::TYPE_ADVISOR,
            'action' => 'reference_data.updated',
            'subject_type' => 'reference_data_entry',
            'subject_id' => 'gdp-quarterly',
            'after' => ['value' => 0.2],
            'request_id' => (string) Str::uuid(),
        ]);

        AuditEvent::query()->create([
            'id' => (string) Str::uuid(),
            'occurred_at' => now()->subMinutes(2),
            'actor_role' => 'system',
            'action' => 'calendar_connection.synced',
            'after' => ['provider' => 'microsoft'],
            'request_id' => (string) Str::uuid(),
        ]);

        $this->actingAsMfa($admin)
            ->get(route('admin.audit-trail.index', [
                'actor' => 'Aroha',
                'action' => 'reference_data',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/audit-trail/Index')
                ->where('events.total', 1)
                ->where('events.current_page', 1)
                ->where('events.last_page', 1)
                ->where('events.data.0.action', 'reference_data.updated')
                ->where('events.data.0.actor.name', 'Aroha Reviewer')
                ->where('events.data.0.actor.email', 'aroha.reviewer@example.test')
                ->where('filters.actor', 'Aroha')
                ->where('filters.action', 'reference_data'),
            );
    }

    public function test_audit_trail_formats_event_times_in_the_nz_display_timezone(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();

        AuditEvent::query()->create([
            'id' => (string) Str::uuid(),
            'occurred_at' => Carbon::parse('2026-08-02 06:48:00', 'UTC'),
            'actor_user_key' => (string) $admin->getKey(),
            'actor_role' => User::TYPE_SUPER_ADMIN,
            'action' => 'audit.timezone_probe',
            'request_id' => (string) Str::uuid(),
        ]);

        $this->actingAsMfa($admin)
            ->get(route('admin.audit-trail.index', ['action' => 'audit.timezone_probe']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.data.0.action', 'audit.timezone_probe')
                ->where('events.data.0.occurred_at_label', '02 Aug 2026, 6:48 PM'),
            );
    }

    public function test_authenticated_404_is_recorded_in_the_audit_trail(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('advisor.entrepreneurs.plans.assessments.store', [
                'entrepreneurProfile' => (string) Str::uuid(),
                'businessPlan' => (string) Str::uuid(),
            ]))
            ->assertNotFound();

        $event = AuditEvent::query()
            ->where('action', 'http.request_failed')
            ->latest('occurred_at')
            ->firstOrFail();

        $this->assertSame((string) $admin->getKey(), $event->actor_user_key);
        $this->assertSame('POST', data_get($event->after, 'context.method'));
        $this->assertStringStartsWith('advisor/entrepreneurs/', (string) data_get($event->after, 'context.path'));
        $this->assertSame(404, data_get($event->after, 'context.status'));
        $this->assertNull(data_get($event->after, 'context.exception'));
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }
}
