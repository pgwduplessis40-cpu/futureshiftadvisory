<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CommunicationPreference;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ThreadedMessagingTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_messaging_rls_app';

    private string $secureRoot;

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secureRoot = storage_path('framework/testing/threaded-messaging');
        File::deleteDirectory($this->secureRoot);
        Config::set('filesystems.disks.secure_local.root', $this->secureRoot);
        Storage::forgetDisk('secure_local');

        $this->seed(RoleSeeder::class);
        Mail::fake();
        app(RequestContext::class)->apply('system', []);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->connectionBypassesRls = $this->currentRoleBypassesRls();
            if ($this->connectionBypassesRls) {
                $this->createNonBypassRole();
            }
        }
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('secure_local');
        File::deleteDirectory($this->secureRoot);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT ON entrepreneur_profiles, message_threads, message_thread_participants, messages FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_advisor_starts_thread_with_attachment_and_client_channel_preference_is_respected(): void
    {
        $advisor = $this->advisor();
        $clientUser = $this->clientUser();
        $clientUser->communicationPreference()->create([
            'channel' => CommunicationPreference::CHANNEL_EMAIL_ONLY,
            'frequency' => CommunicationPreference::FREQUENCY_DAILY,
            'timezone' => 'Pacific/Auckland',
        ]);
        $client = $this->clientWithUsers($clientUser, $advisor);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.messages.store', $client), [
                'subject' => 'April cashflow',
                'body' => 'Please review the attached cashflow statement.',
                'attachments' => [
                    UploadedFile::fake()->createWithContent('cashflow.txt', 'Cashflow statement is stable.'),
                ],
            ])
            ->assertRedirect();

        $thread = MessageThread::query()->firstOrFail();
        $message = Message::query()->firstOrFail();
        $document = Document::query()->firstOrFail();

        $this->assertSame($client->id, $thread->client_id);
        $this->assertSame($thread->id, $message->thread_id);
        $this->assertSame([$document->id], $message->attachments);
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'category' => Document::CATEGORY_MESSAGE_ATTACHMENT,
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
        $this->assertDatabaseHas('document_verifications', [
            'document_id' => $document->id,
            'claim_source' => 'message_attachment',
        ]);

        $notification = $clientUser->notifications()->firstOrFail();
        $decision = json_decode((string) $notification->channel_decision, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('message.new', $notification->type);
        $this->assertSame(CommunicationPreference::CHANNEL_EMAIL_ONLY, $decision['preference_channel']);
        $this->assertTrue($decision['email_deferred']);
        $this->assertFalse($decision['mail_now']);
        Mail::assertNothingSent();
    }

    public function test_client_replies_from_portal_and_advisor_receives_notification(): void
    {
        $advisor = $this->advisor();
        $clientUser = $this->clientUser();
        $client = $this->clientWithUsers($clientUser, $advisor);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.messages.store', $client), [
                'subject' => 'Due diligence',
                'body' => 'Could you confirm the latest payroll file?',
            ])
            ->assertRedirect();

        $thread = MessageThread::query()->firstOrFail();
        $advisor->notifications()->delete();

        $this->actingAsMfa($clientUser)
            ->post(route('portal.messages.reply', $thread), [
                'body' => 'Confirmed. I have added a short note here.',
            ])
            ->assertRedirect(route('portal.messages.show', $thread));

        $this->assertSame(2, Message::query()->where('thread_id', $thread->id)->count());
        $this->assertSame('message.new', $advisor->notifications()->firstOrFail()->type);

        $this->actingAsMfa($clientUser)
            ->get(route('portal.messages.show', $thread))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/messages/Index')
                ->where('selectedThread.id', $thread->id)
                ->has('selectedThread.messages', 2));
    }

    public function test_entrepreneur_starts_thread_from_portal_and_advisor_is_notified(): void
    {
        $advisor = $this->advisor();
        $entrepreneur = $this->entrepreneurUser();
        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->getKey(),
            'user_id' => $entrepreneur->getKey(),
            'name' => 'Portal Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'A new export forecasting idea.',
        ]);

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.messages.store'), [
                'subject' => 'Plan evidence review',
                'body' => 'I have uploaded a new customer interview summary.',
            ])
            ->assertRedirect();

        $thread = MessageThread::query()->firstOrFail();

        $this->assertNull($thread->client_id);
        $this->assertSame($profile->id, $thread->entrepreneur_profile_id);
        $this->assertSame('message.new', $advisor->notifications()->firstOrFail()->type);

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.messages.show', $thread))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/messages/Index')
                ->where('client.legal_name', 'Portal Founder')
                ->where('client.engagement_type_label', 'Entrepreneur portal')
                ->where('backHref', route('portal.entrepreneur.dashboard', absolute: false))
                ->where('selectedThread.id', $thread->id)
                ->has('selectedThread.messages', 1));
    }

    public function test_message_threads_are_scoped_by_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Message RLS assertions require Postgres.');
        }

        $advisor = $this->advisor();
        $clientAUser = $this->clientUser('client-a@example.com');
        $clientBUser = $this->clientUser('client-b@example.com');
        $clientA = $this->clientWithUsers($clientAUser, $advisor, 'Client A Limited');
        $clientB = $this->clientWithUsers($clientBUser, $advisor, 'Client B Limited');

        app(RequestContext::class)->apply('system', []);
        $threadA = MessageThread::query()->create([
            'client_id' => $clientA->id,
            'created_by_user_id' => $advisor->getKey(),
            'subject' => 'Client A thread',
            'last_activity_at' => now(),
        ]);
        $threadB = MessageThread::query()->create([
            'client_id' => $clientB->id,
            'created_by_user_id' => $advisor->getKey(),
            'subject' => 'Client B thread',
            'last_activity_at' => now(),
        ]);

        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()], (string) $advisor->getKey());
        $visibleIds = $this->withRlsRole(fn (): array => MessageThread::query()
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $this->assertContains($threadA->id, $visibleIds);
        $this->assertNotContains($threadB->id, $visibleIds);
    }

    private function advisor(string $email = 'advisor@example.com'): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function clientUser(string $email = 'client@example.com'): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        return $user;
    }

    private function entrepreneurUser(string $email = 'entrepreneur@example.com'): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $user->assignRole(User::TYPE_ENTREPRENEUR);

        return $user;
    }

    private function clientWithUsers(User $clientUser, User $advisor, string $name = 'Messaging Test Limited'): Client
    {
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $clientUser->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $clientUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $client;
    }

    private function currentRoleBypassesRls(): bool
    {
        $role = DB::selectOne(
            'SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user'
        );

        return (bool) ($role->rolsuper ?? false) || (bool) ($role->rolbypassrls ?? false);
    }

    private function createNonBypassRole(): void
    {
        DB::unprepared(sprintf(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '%1$s') THEN
                    CREATE ROLE %1$s NOLOGIN NOBYPASSRLS;
                END IF;
            END
            $$;

            GRANT USAGE ON SCHEMA public TO %1$s;
            GRANT SELECT ON entrepreneur_profiles, message_threads, message_thread_participants, messages TO %1$s;
        SQL, self::RLS_APP_ROLE));
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    private function withRlsRole(callable $callback): mixed
    {
        if (! $this->connectionBypassesRls) {
            return $callback();
        }

        DB::statement('SET ROLE '.self::RLS_APP_ROLE);
        $usesSavepoint = DB::transactionLevel() > 0;

        if ($usesSavepoint) {
            DB::statement('SAVEPOINT messaging_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT messaging_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT messaging_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
