<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\PanelAgreement;
use App\Models\PanelMember;
use App\Models\ProspectLead;
use App\Models\Referral;
use App\Models\ReverseReferral;
use App\Models\User;
use App\Services\Panels\PanelAccessException;
use App\Services\Panels\PanelOnboarding;
use App\Services\Panels\ReferralLifecycle;
use App\Services\Pdf\PdfRenderer;
use App\Services\Security\InviteIssuer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PanelFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_panel_foundation_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Mail::fake();
        Storage::fake('secure_local');

        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->connectionBypassesRls = $this->currentRoleBypassesRls();

            if ($this->connectionBypassesRls) {
                $this->createNonBypassRole();
            }
        }
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT ON panel_members, panel_agreements, referrals, referral_messages, reverse_referrals FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_invite_application_approval_and_signed_agreement_gate_portal_access(): void
    {
        $advisor = $this->advisor();
        $issued = app(InviteIssuer::class)->issue(
            email: 'broker-panel@example.test',
            targetUserType: User::TYPE_BROKER,
            targetRole: User::TYPE_BROKER,
            issuedBy: $advisor,
        );
        $broker = $this->panelUser('broker-panel@example.test', User::TYPE_BROKER);
        $issued->invite->markAccepted($broker);
        $onboarding = app(PanelOnboarding::class);

        $member = $onboarding->submitApplication($broker, PanelMember::TYPE_BROKER, [
            'trading_name' => 'Panel Broker',
            'regions' => ['Auckland'],
        ]);

        try {
            $onboarding->assertPortalAccess($broker);
            $this->fail('Unsigned panel member should not have portal access.');
        } catch (PanelAccessException $e) {
            $this->assertStringContainsString('active signed agreement', $e->getMessage());
        }

        $agreement = $onboarding->approve($member, $advisor);
        $signed = $onboarding->signAgreement($agreement, $broker);
        $activeMember = $onboarding->assertPortalAccess($broker);

        $this->assertSame(PanelMember::STATUS_ACTIVE, $activeMember->status);
        $this->assertSame(PanelAgreement::STATUS_SIGNED, $signed->status);
        $this->assertNotNull($signed->pdf_sha256_envelope);
        $this->assertSame($broker->getKey(), $signed->signed_by_user_id);
        Storage::disk('secure_local')->assertExists($signed->pdf_path);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'panel.agreement_signed',
            'subject_id' => $signed->id,
        ]);
        $this->assertDatabaseHas('invite_tokens', [
            'id' => $issued->invite->id,
            'accepted_by_user_id' => $broker->getKey(),
        ]);
    }

    public function test_referral_stage_transitions_messages_and_reverse_referral_are_audited(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor();
        $broker = $this->activePanelMember(User::TYPE_BROKER, 'broker-lifecycle@example.test');
        $lifecycle = app(ReferralLifecycle::class);

        $referral = $lifecycle->create($client, $broker, $advisor, [
            'need' => 'Insurance review',
        ]);
        $referral = $lifecycle->transition($referral, Referral::STAGE_SENT, $advisor);
        $referral = $lifecycle->transition($referral, Referral::STAGE_ACCEPTED, $broker->user);
        $message = $lifecycle->message($referral, $broker->user, 'We can help with this case.');
        $reverse = $lifecycle->reverseReferral(
            member: $broker,
            targetType: ReverseReferral::TARGET_PROSPECT,
            name: 'Reverse Lead',
            email: 'reverse@example.test',
            company: 'Reverse Co',
            payload: ['message' => 'Needs advisory support.'],
        );

        $this->assertSame(Referral::STAGE_ACCEPTED, $referral->stage);
        $this->assertSame($client->id, $message->client_id);
        $this->assertSame(ReverseReferral::TARGET_PROSPECT, $reverse->target_type);
        $lead = ProspectLead::query()->firstOrFail();
        $this->assertSame('reverse_referral', $lead->source);
        $this->assertNull($lead->invite_token_id);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'referral.stage_changed',
            'subject_id' => $referral->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'reverse_referral.created',
            'subject_id' => $reverse->id,
        ]);
    }

    public function test_panel_rows_are_isolated_between_panel_users_by_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Panel RLS assertions require Postgres.');
        }

        [$advisor, $client] = $this->clientWithAdvisor('panel-rls-advisor@example.test');
        $broker = $this->activePanelMember(User::TYPE_BROKER, 'panel-rls-broker@example.test');
        $coach = $this->activePanelMember(User::TYPE_COACH, 'panel-rls-coach@example.test');
        app(ReferralLifecycle::class)->create($client, $broker, $advisor);
        app(ReferralLifecycle::class)->create($client, $coach, $advisor);

        app(RequestContext::class)->apply(User::TYPE_BROKER, [], (string) $broker->user_id);

        $visibleMemberIds = $this->withRlsRole(fn (): array => DB::table('panel_members')->pluck('id')->map(fn (mixed $id): string => (string) $id)->all());
        $visibleReferralMemberIds = $this->withRlsRole(fn (): array => DB::table('referrals')->pluck('panel_member_id')->map(fn (mixed $id): string => (string) $id)->all());

        $this->assertContains($broker->id, $visibleMemberIds);
        $this->assertNotContains($coach->id, $visibleMemberIds);
        $this->assertContains($broker->id, $visibleReferralMemberIds);
        $this->assertNotContains($coach->id, $visibleReferralMemberIds);
    }

    private function activePanelMember(string $type, string $email): PanelMember
    {
        $user = $this->panelUser($email, $type);
        $onboarding = app(PanelOnboarding::class);
        $member = $onboarding->submitApplication($user, $type, ['fixture' => true]);
        $agreement = $onboarding->approve($member, $this->advisor('approver-'.$email));
        $onboarding->signAgreement($agreement, $user);

        return $member->refresh()->load('user');
    }

    private function panelUser(string $email, string $type): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => $type,
            'primary_role' => $type,
        ]);
        $user->assignRole($type);

        return $user;
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientWithAdvisor(string $advisorEmail = 'panel-advisor@example.test'): array
    {
        $advisor = $this->advisor($advisorEmail);
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Panel Client Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client];
    }

    private function advisor(string $email = 'panel-foundation-advisor@example.test'): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
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
            GRANT SELECT ON panel_members, panel_agreements, referrals, referral_messages, reverse_referrals TO %1$s;
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
            DB::statement('SAVEPOINT panel_foundation_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT panel_foundation_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT panel_foundation_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
